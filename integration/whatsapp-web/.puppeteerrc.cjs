// Puppeteer configuration for Railway/cloud deployment
const {join} = require('path');

module.exports = {
  cacheDirectory: join(__dirname, '.cache', 'puppeteer'),
};
