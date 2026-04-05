module.exports = {
  apps: [
    {
      name: 'hidroponik-mqtt',
      cwd: __dirname,
      script: 'php',
      args: 'artisan mqtt:subscribe',
      interpreter: 'none',
      autorestart: true,
      watch: false,
      max_restarts: 20,
      restart_delay: 3000,
      out_file: 'storage/logs/mqtt-service.log',
      error_file: 'storage/logs/mqtt-service-error.log',
      time: true,
      env: {
        APP_ENV: 'production'
      }
    }
  ]
};
