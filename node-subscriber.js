// Node.js MQTT subscriber: apply calibration then save to MySQL
// Requirements: npm install mqtt mysql2

import mqtt from 'mqtt';
import mysql from 'mysql2/promise';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

    
    function resolveMirrorDevice(kebun) {
      const device = String(kebun || '').trim().toLowerCase();
      if (['kebun-1', 'kebun-a', 'a'].includes(device)) {
        return 'kebun-2';
      }
      return null;
    }
// __dirname replacement for ESM
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// DB config (adjust if needed)
const DB_CONFIG = {
  host: process.env.DB_HOST || '127.0.0.1',
  user: process.env.DB_USERNAME || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_DATABASE || 'hidroponik_db',
  port: parseInt(process.env.DB_PORT || '3306', 10)
};

// MQTT config
const MQTT_URL = process.env.MQTT_URL || 'mqtt://broker.emqx.io:1883';
const MQTT_CLIENT_ID = 'node-subscriber-' + Math.random().toString(16).slice(2);
const MQTT_SUB_FILTER = process.env.MQTT_SUB_FILTER || 'hidroganik/+/publish';

// Load save interval setting
let currentInterval = 'realtime';
let lastConfigCheck = 0;
const CONFIG_CHECK_INTERVAL = 1000; // Check config every 1 second for quicker response

function loadSaveInterval() {
  try {
    // Resolve relative to this script directory to avoid CWD issues
    // Laravel 11 stores local files in storage/app/private by default
    const configPath = path.resolve(__dirname, 'storage', 'app', 'private', 'mqtt_save_interval.json');
    if (fs.existsSync(configPath)) {
      const data = JSON.parse(fs.readFileSync(configPath, 'utf8'));
      const newInterval = data.interval || 'realtime';
      if (newInterval !== currentInterval) {
        console.log(`[CONFIG] Interval changed from ${currentInterval} to ${newInterval}`);
        currentInterval = newInterval;
        // Clear last save times when interval changes
        lastSaveTime.clear();
      }
      return newInterval;
    }
  } catch (e) {
    console.warn('[CONFIG] Could not load save interval, using realtime:', e.message);
  }
  return 'realtime';
}

// Initial load
currentInterval = loadSaveInterval();

// Check if should save based on interval
const lastSaveTime = new Map();
function shouldSave(kebun) {
  // Reload config periodically
  const now = Date.now();
  if (now - lastConfigCheck > CONFIG_CHECK_INTERVAL) {
    loadSaveInterval();
    lastConfigCheck = now;
    // Optional debug: show current interval after reload
    // console.log('[CONFIG] Current interval:', currentInterval);
  }
  
  if (currentInterval === 'realtime') return true;
  
  const intervalMinutes = parseInt(currentInterval, 10);
  if (isNaN(intervalMinutes)) return true;
  
  const lastSave = lastSaveTime.get(kebun) || 0;
  const diff = now - lastSave;
  const threshold = intervalMinutes * 60 * 1000;
  
  if (diff >= threshold) {
    lastSaveTime.set(kebun, now);
    return true;
  }
  return false;
}

async function main() {
  const pool = await mysql.createPool({
    ...DB_CONFIG,
    waitForConnections: true,
    connectionLimit: 5,
    queueLimit: 0,
  });


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

          const mirrorSave = await saveTelemetry(mirroredPayload);
          if (mirrorSave.inserted) {
            console.log('[DB] Saved mirror row:', mirrorKebun, 'tds=', mirroredPayload.tds, 'suhu=', mirroredPayload.suhu, 'interval=', currentInterval);
          } else {
            console.log('[DB] Skipped mirror duplicate:', mirrorKebun, 'at', recordedAt.toISOString());
          }
        }
  // Cache calibration per kebun for 60s
  const calibrationCache = new Map();
  const CAL_CACHE_MS = 60_000;

  async function getCalibration(kebun) {
    const key = kebun;
    const now = Date.now();
    const cached = calibrationCache.get(key);
    if (cached && (now - cached.ts) < CAL_CACHE_MS) {
      return cached.value;
    }
    const [rows] = await pool.query(
      'SELECT kebun, tds_multiplier, suhu_correction FROM calibration_settings WHERE kebun = ? LIMIT 1',
      [kebun]
    );
    const value = rows && rows[0] ? rows[0] : { kebun, tds_multiplier: 1.0, suhu_correction: 0.0 };
    calibrationCache.set(key, { value, ts: now });
    return value;
  }

  async function saveTelemetry(payload) {
    const recordedAt = payload.recorded_at ?? new Date();

    const [existing] = await pool.query(
      'SELECT id FROM telemetries WHERE kebun = ? AND recorded_at = ? LIMIT 1',
      [payload.kebun ?? null, recordedAt]
    );

    if (Array.isArray(existing) && existing.length > 0) {
      return { inserted: false, id: existing[0].id };
    }

    const sql = `INSERT INTO telemetries (kebun, ph, tds, suhu, cal_ph_netral, cal_ph_asam, cal_tds_k, tds_mentah, raw_payload, recorded_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`;
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

  const client = mqtt.connect(MQTT_URL, {
    clientId: MQTT_CLIENT_ID,
    clean: true,
    reconnectPeriod: 2000,
    keepalive: 60,
  });

  client.on('connect', () => {
    console.log('[MQTT] Connected as', MQTT_CLIENT_ID);
    client.subscribe(MQTT_SUB_FILTER, { qos: 0 }, (err) => {
      if (err) console.error('[MQTT] Subscribe error:', err);
      else console.log('[MQTT] Subscribed to', MQTT_SUB_FILTER);
    });
  });

  client.on('message', async (topic, message) => {
    try {
      const raw = JSON.parse(message.toString());
      // Support both Indonesian (perangkat) and English (kebun) fields, fallback to topic
      const kebun = raw.perangkat || raw.kebun || topic.split('/')[1] || null;

      // Timestamp - support both Indonesian (tanggal/waktu) and English (date/time) fields
      let recordedAt = new Date();
      const dateField = raw.tanggal || raw.date;
      const timeField = raw.waktu || raw.time;
      if (dateField && timeField) {
        const dtStr = `${dateField} ${timeField}`;
        const parsed = new Date(dtStr.replace(' ', 'T'));
        if (!isNaN(parsed.getTime())) recordedAt = parsed;
      }

      // Load calibration
      const cal = await getCalibration(kebun || 'kebun-a');

      // Apply calibration
      const tdsRaw = numOrNull(raw.tds);
      const suhuRaw = numOrNull(raw.suhu);
      const tdsCal = tdsRaw != null && cal ? Math.round(tdsRaw * Number(cal.tds_multiplier || 1)) : tdsRaw;
      const suhuCal = suhuRaw != null && cal ? round2(suhuRaw + Number(cal.suhu_correction || 0)) : suhuRaw;

      // Publish data mentah + terkalibrasi ke topic preview untuk halaman Kalibrasi
      const previewData = {
        kebun,
        tds_mentah: tdsRaw,
        suhu_mentah: suhuRaw,
        tds: tdsCal,
        suhu: suhuCal,
        ph: numOrNull(raw.ph),
      };
      const previewTopic = `hidroganik/${kebun}/preview`;
      client.publish(previewTopic, JSON.stringify(previewData), { qos: 0 });
      console.log('[PUBLISH]', previewTopic, previewData);

      // Check save interval setting
      if (!shouldSave(kebun)) {
        console.log('[SKIP] Save skipped due to interval setting:', currentInterval);
        return;
      }

      const payload = {
        kebun,
        ph: numOrNull(raw.ph),
        tds: tdsCal,
        suhu: suhuCal,
        cal_ph_netral: numOrNull(raw.cal_ph_netral),
        cal_ph_asam: numOrNull(raw.cal_ph_asam),
        cal_tds_k: numOrNull(raw.cal_tds_k),
        tds_mentah: tdsRaw, // keep raw for reference
        raw_payload: JSON.stringify(raw),
        recorded_at: recordedAt,
      };

      const saveResult = await saveTelemetry(payload);
      if (saveResult.inserted) {
        console.log('[DB] Saved row:', kebun, 'tds=', payload.tds, 'suhu=', payload.suhu, 'interval=', currentInterval);
      } else {
        console.log('[DB] Skipped duplicate:', kebun, 'at', recordedAt.toISOString());
      }
    } catch (e) {
      console.error('[ERROR] message handling failed:', e && e.message ? e.message : e);
    }
  });

  client.on('error', (err) => console.error('[MQTT] Error:', err.message));
  client.on('reconnect', () => console.log('[MQTT] Reconnecting...'));
  client.on('close', () => console.log('[MQTT] Connection closed'));
}

function numOrNull(v) {
  if (v === null || v === undefined) return null;
  const n = Number(v);
  return isNaN(n) ? null : n;
}
function round2(n) { return Math.round(Number(n) * 100) / 100; }

main().catch((e) => {
  console.error('Fatal error:', e);
  process.exit(1);
});
