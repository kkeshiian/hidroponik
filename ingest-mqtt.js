
/**
 * MQTT Data Ingestion Service for Hidroponik System
 * 
 * Service untuk menerima data sensor dari MQTT broker dan menyimpan ke MySQL
 * Berjalan sebagai background service yang terus-menerus listen 24/7
 * 
 * Cara menjalankan:
 *   npm run mqtt:start
 * 
 * Atau langsung:
 *   node ingest-mqtt.js
 */

import mqtt from 'mqtt';
import mysql from 'mysql2/promise';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import dotenv from 'dotenv';

function resolveMirrorDevice(kebun) {
  const device = String(kebun || '').trim().toLowerCase();
  if (['kebun-1', 'kebun-a', 'a'].includes(device)) {
    return 'kebun-2';
  }
  return null;
}

// Load .env file
dotenv.config();

// ESM __dirname replacement
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// ==================== CONFIGURATION ====================

const CONFIG = {
  // Database
  db: {
    host: process.env.DB_HOST || '127.0.0.1',
    user: process.env.DB_USERNAME || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_DATABASE || 'hidroponik_db',
    port: parseInt(process.env.DB_PORT || '3306', 10),
    connectionLimit: 5,
    waitForConnections: true,
    queueLimit: 0,
  },
  
  // MQTT
  mqtt: {
    broker: process.env.MQTT_BROKER || 'broker.emqx.io',
    port: parseInt(process.env.MQTT_PORT || '1883', 10),
    username: process.env.MQTT_USERNAME || null,
    password: process.env.MQTT_PASSWORD || null,
    topic: process.env.MQTT_TOPIC || 'hidroganik/+/publish',
    clientId: `hidroponik_ingest_${Math.random().toString(16).slice(2, 10)}`,
    keepalive: 60,
    reconnectPeriod: 2000,
  },
  
  // Application
  app: {
    saveIntervalCheckMs: 1000, // Check config every 1 second
    calibrationCacheMs: 60000, // Cache calibration for 60 seconds
    logTimestamps: true,
  }
};

// ==================== STATE MANAGEMENT ====================

let currentInterval = 'realtime';
let lastConfigCheck = 0;
const lastSaveTime = new Map();
const calibrationCache = new Map();

// ==================== HELPER FUNCTIONS ====================

/**
 * Format timestamp untuk logging
 */
function timestamp() {
  if (!CONFIG.app.logTimestamps) return '';
  return `[${new Date().toISOString().replace('T', ' ').slice(0, -5)}]`;
}

/**
 * Log dengan timestamp
 */
function log(level, ...args) {
  const levels = {
    info: '✓',
    warn: '⚠',
    error: '✗',
    debug: '→'
  };
  const icon = levels[level] || '•';
  console.log(timestamp(), icon, ...args);
}

/**
 * Convert value to number or null
 */
function numOrNull(value) {
  if (value === null || value === undefined) return null;
  const num = Number(value);
  return isNaN(num) ? null : num;
}

/**
 * Round to 2 decimal places
 */
function round2(num) {
  return Math.round(Number(num) * 100) / 100;
}

/**
 * Load save interval setting from file
 */
function loadSaveInterval() {
  try {
    const configPath = path.resolve(__dirname, 'storage', 'app', 'private', 'mqtt_save_interval.json');
    
    if (fs.existsSync(configPath)) {
      const data = JSON.parse(fs.readFileSync(configPath, 'utf8'));
      const newInterval = data.interval || 'realtime';
      
      if (newInterval !== currentInterval) {
        log('info', `Interval changed: ${currentInterval} → ${newInterval}`);
        currentInterval = newInterval;
        lastSaveTime.clear(); // Clear save times when interval changes
      }
      
      return newInterval;
    }
  } catch (error) {
    log('warn', 'Could not load save interval config:', error.message);
  }
  
  return 'realtime';
}

/**
 * Check if data should be saved based on interval setting
 */
function shouldSaveData(kebun) {
  // Reload config periodically
  const now = Date.now();
  if (now - lastConfigCheck > CONFIG.app.saveIntervalCheckMs) {
    loadSaveInterval();
    lastConfigCheck = now;
  }
  
  // Realtime = always save
  if (currentInterval === 'realtime') return true;
  
  // Parse interval in minutes
  const intervalMinutes = parseInt(currentInterval, 10);
  if (isNaN(intervalMinutes)) return true;
  
  // Check last save time for this kebun
  const lastSave = lastSaveTime.get(kebun) || 0;
  const elapsedMs = now - lastSave;
  const thresholdMs = intervalMinutes * 60 * 1000;
  
  if (elapsedMs >= thresholdMs) {
    lastSaveTime.set(kebun, now);
    return true;
  }
  
  return false;
}

// ==================== DATABASE FUNCTIONS ====================

/**
 * Get calibration settings from database (with caching)
 */
async function getCalibration(pool, kebun) {
  const now = Date.now();
  const cached = calibrationCache.get(kebun);
  
  // Return cached if still fresh
  if (cached && (now - cached.timestamp) < CONFIG.app.calibrationCacheMs) {
    return cached.value;
  }
  
  try {
    const [rows] = await pool.query(
      'SELECT kebun, tds_multiplier, suhu_correction FROM calibration_settings WHERE kebun = ? LIMIT 1',
      [kebun]
    );
    
    const value = rows && rows[0] 
      ? rows[0] 
      : { kebun, tds_multiplier: 1.0, suhu_correction: 0.0 };
    
    calibrationCache.set(kebun, { value, timestamp: now });
    return value;
    
  } catch (error) {
    log('error', 'Failed to get calibration:', error.message);
    return { kebun, tds_multiplier: 1.0, suhu_correction: 0.0 };
  }
}

/**
 * Save telemetry data to database
 */
async function saveTelemetry(pool, payload) {
  const recordedAt = payload.recorded_at ?? new Date();

  const [existing] = await pool.query(
    'SELECT id FROM telemetries WHERE kebun = ? AND recorded_at = ? LIMIT 1',
    [payload.kebun ?? null, recordedAt]
  );

  if (Array.isArray(existing) && existing.length > 0) {
    return { inserted: false, id: existing[0].id };
  }

  const sql = `
    INSERT INTO telemetries (
      kebun, ph, tds, suhu, 
      cal_ph_netral, cal_ph_asam, cal_tds_k, tds_mentah, raw_payload,
      recorded_at, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
  `;
  
  const params = [
    payload.kebun ?? null,
    payload.ph ?? null,
    payload.tds ?? null,
    payload.suhu ?? null,
    payload.cal_ph_netral ?? null,
    payload.cal_ph_asam ?? null,
    payload.cal_tds_k ?? null,
    payload.tds_mentah ?? null,
    payload.raw_payload ?? null,
    recordedAt,
  ];
  
  const [result] = await pool.execute(sql, params);
  return { inserted: true, id: result?.insertId ?? null };
}

// ==================== MQTT MESSAGE HANDLER ====================

/**
 * Handle incoming MQTT message
 */
async function handleMessage(pool, client, topic, message) {
  try {
    // Parse JSON message
    const raw = JSON.parse(message.toString());
    
    // Extract kebun from topic (hidroganik/[kebun]/publish) or from message field
    const topicParts = topic.split('/');
    const kebun = raw.perangkat || raw.kebun || topicParts[1] || 'unknown';
    
    log('debug', `Received from ${topic}`);
    
    // Parse timestamp - support both Indonesian (tanggal/waktu) and English (date/time) fields
    let recordedAt = new Date();
    const dateField = raw.tanggal || raw.date;
    const timeField = raw.waktu || raw.time;
    if (dateField && timeField) {
      const dtStr = `${dateField} ${timeField}`;
      const parsed = new Date(dtStr.replace(' ', 'T'));
      if (!isNaN(parsed.getTime())) {
        recordedAt = parsed;
      }
    }
    
    // Load calibration settings
    const cal = await getCalibration(pool, kebun);
    
    // Extract and calibrate sensor values
    const tdsRaw = numOrNull(raw.tds);
    const suhuRaw = numOrNull(raw.suhu);
    const phRaw = numOrNull(raw.ph);
    
    // Apply calibration
    const tdsCalibrated = tdsRaw != null && cal 
      ? Math.round(tdsRaw * Number(cal.tds_multiplier || 1)) 
      : tdsRaw;
      
    const suhuCalibrated = suhuRaw != null && cal 
      ? round2(suhuRaw + Number(cal.suhu_correction || 0)) 
      : suhuRaw;
    
    // Publish preview data for calibration page (real-time preview)
    const previewData = {
      kebun,
      tds_mentah: tdsRaw,
      suhu_mentah: suhuRaw,
      tds: tdsCalibrated,
      suhu: suhuCalibrated,
      ph: phRaw,
    };
    
    const previewTopic = `hidroganik/${kebun}/preview`;
    client.publish(previewTopic, JSON.stringify(previewData), { qos: 0 });
    log('debug', `Published preview to ${previewTopic}`);
    
    // Check if data should be saved based on interval
    if (!shouldSaveData(kebun)) {
      log('debug', `Save skipped (interval: ${currentInterval})`);
      return;
    }
    
    // Prepare payload for database
    const payload = {
      kebun,
      ph: phRaw,
      tds: tdsCalibrated,
      suhu: suhuCalibrated,
      cal_ph_netral: numOrNull(raw.cal_ph_netral),
      cal_ph_asam: numOrNull(raw.cal_ph_asam),
      cal_tds_k: numOrNull(raw.cal_tds_k),
      tds_mentah: tdsRaw,
      raw_payload: JSON.stringify(raw),
      recorded_at: recordedAt,
    };
    
    // Save to database
    const saveResult = await saveTelemetry(pool, payload);

    if (saveResult.inserted) {
      log('info', `Saved: ${kebun} | pH=${payload.ph} TDS=${payload.tds} Suhu=${payload.suhu}°C`);
    } else {
      log('debug', `Duplicate skipped: ${kebun} @ ${new Date(recordedAt).toISOString()}`);
    }

    const mirrorKebun = resolveMirrorDevice(kebun);
    if (mirrorKebun) {
      const mirroredPayload = {
        ...payload,
        kebun: mirrorKebun,
        raw_payload: JSON.stringify({
          ...raw,
          kebun: mirrorKebun,
          mirrored_from: kebun,
        }),
      };

      const mirrorSave = await saveTelemetry(pool, mirroredPayload);
      if (mirrorSave.inserted) {
        log('info', `Saved mirror: ${mirrorKebun} | pH=${mirroredPayload.ph} TDS=${mirroredPayload.tds} Suhu=${mirroredPayload.suhu}°C`);
      } else {
        log('debug', `Duplicate mirror skipped: ${mirrorKebun} @ ${new Date(recordedAt).toISOString()}`);
      }
    }
    
  } catch (error) {
    log('error', 'Message handling failed:', error.message);
  }
}

// ==================== MAIN SERVICE ====================

async function startService() {
  log('info', '='.repeat(60));
  log('info', '  MQTT Data Ingestion Service - Hidroponik System');
  log('info', '='.repeat(60));
  log('info', '');
  
  try {
    // Create database connection pool
    log('info', 'Connecting to database...');
    const pool = mysql.createPool(CONFIG.db);
    
    // Test database connection
    const connection = await pool.getConnection();
    await connection.ping();
    connection.release();
    log('info', `Database connected: ${CONFIG.db.database}@${CONFIG.db.host}`);
    
    // Load initial save interval
    currentInterval = loadSaveInterval();
    log('info', `Save interval: ${currentInterval === 'realtime' ? 'Realtime' : currentInterval + ' minutes'}`);
    
    // Create MQTT client
    log('info', 'Connecting to MQTT broker...');
    const mqttUrl = `mqtt://${CONFIG.mqtt.broker}:${CONFIG.mqtt.port}`;
    
    const mqttOptions = {
      clientId: CONFIG.mqtt.clientId,
      clean: true,
      keepalive: CONFIG.mqtt.keepalive,
      reconnectPeriod: CONFIG.mqtt.reconnectPeriod,
    };
    
    if (CONFIG.mqtt.username && CONFIG.mqtt.password) {
      mqttOptions.username = CONFIG.mqtt.username;
      mqttOptions.password = CONFIG.mqtt.password;
    }
    
    const client = mqtt.connect(mqttUrl, mqttOptions);
    
    // MQTT event handlers
    client.on('connect', () => {
      log('info', `MQTT connected: ${CONFIG.mqtt.broker}:${CONFIG.mqtt.port}`);
      log('info', `Client ID: ${CONFIG.mqtt.clientId}`);
      
      // Subscribe to topic
      client.subscribe(CONFIG.mqtt.topic, { qos: 0 }, (error) => {
        if (error) {
          log('error', 'Subscribe failed:', error.message);
        } else {
          log('info', `Subscribed to: ${CONFIG.mqtt.topic}`);
          log('info', '');
          log('info', 'Service is running... Press CTRL+C to stop');
          log('info', '-'.repeat(60));
        }
      });
    });
    
    client.on('message', (topic, message) => {
      handleMessage(pool, client, topic, message);
    });
    
    client.on('error', (error) => {
      log('error', 'MQTT error:', error.message);
    });
    
    client.on('reconnect', () => {
      log('warn', 'Reconnecting to MQTT broker...');
    });
    
    client.on('close', () => {
      log('warn', 'MQTT connection closed');
    });
    
    client.on('offline', () => {
      log('warn', 'MQTT client offline');
    });
    
    // Graceful shutdown
    process.on('SIGINT', async () => {
      log('info', '');
      log('info', 'Shutting down gracefully...');
      
      try {
        client.end();
        await pool.end();
        log('info', 'Service stopped successfully');
        process.exit(0);
      } catch (error) {
        log('error', 'Error during shutdown:', error.message);
        process.exit(1);
      }
    });
    
  } catch (error) {
    log('error', 'Failed to start service:', error.message);
    process.exit(1);
  }
}

// Start the service
startService().catch((error) => {
  console.error('Fatal error:', error);
  process.exit(1);
});
