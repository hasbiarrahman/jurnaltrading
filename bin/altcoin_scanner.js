import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const resultsPath = path.resolve(__dirname, '../storage/app/altcoin_scan_results.json');

function calculateRSI(closes, period = 14) {
  let rsi = Array(closes.length).fill(null);
  let gains = [];
  let losses = [];
  for (let i = 1; i < closes.length; i++) {
    let diff = closes[i] - closes[i - 1];
    gains.push(diff > 0 ? diff : 0);
    losses.push(diff < 0 ? -diff : 0);
  }
  
  let avgGain = gains.slice(0, period).reduce((a, b) => a + b, 0) / period;
  let avgLoss = losses.slice(0, period).reduce((a, b) => a + b, 0) / period;
  
  rsi[period] = avgLoss === 0 ? 100 : 100 - (100 / (1 + avgGain / avgLoss));
  
  for (let i = period + 1; i < closes.length; i++) {
    avgGain = (avgGain * (period - 1) + gains[i - 1]) / period;
    avgLoss = (avgLoss * (period - 1) + losses[i - 1]) / period;
    rsi[i] = avgLoss === 0 ? 100 : 100 - (100 / (1 + avgGain / avgLoss));
  }
  return rsi;
}

function calculateStochRSI(rsiValues, period = 14, smoothK = 3) {
  let cleanRsi = rsiValues.filter(v => v !== null);
  let stochRsi = [];
  for (let i = period - 1; i < cleanRsi.length; i++) {
    let rsiWindow = cleanRsi.slice(i - period + 1, i + 1);
    let minRsi = Math.min(...rsiWindow);
    let maxRsi = Math.max(...rsiWindow);
    let value = (maxRsi === minRsi) ? 100 : ((cleanRsi[i] - minRsi) / (maxRsi - minRsi)) * 100;
    stochRsi.push(value);
  }
  
  let k = Array(stochRsi.length).fill(null);
  for (let i = smoothK - 1; i < stochRsi.length; i++) {
    let sum = stochRsi.slice(i - smoothK + 1, i + 1).reduce((a, b) => a + b, 0);
    k[i] = sum / smoothK;
  }
  return k;
}

const MEMECOINS = [
  "DOGE", "SHIB", "PEPE", "BONK", "WIF", "FLOKI", "MEME", "STAY", "RAVE", 
  "BOME", "BABYDOGE", "TURBO", "MYRO", "COQ", "MOG", "WEN", "SLERF", 
  "POPCAT", "BRETT", "MEW", "DEGEN", "SNEK", "COCOS", "LUNC", "USTC"
];

async function main() {
  try {
    console.log("Fetching tickers from KuCoin...");
    const res = await fetch("https://api.kucoin.com/api/v1/market/allTickers");
    const json = await res.json();
    if (json.code !== "200000" || !json.data || !json.data.ticker) {
      console.log("Failed to fetch tickers:", json);
      process.exit(1);
    }
    
    const exclude = ["BTC-USDT", "USDC-USDT", "DAI-USDT", "USDT-DAI", "EUR-USDT", "GBP-USDT"];
    let pairs = json.data.ticker
      .filter(t => {
        if (!t.symbol.endsWith("-USDT") || exclude.includes(t.symbol)) return false;
        const base = t.symbol.split("-")[0];
        return !MEMECOINS.includes(base);
      })
      .map(t => ({
        symbol: t.symbol,
        volume: parseFloat(t.volValue)
      }));
      
    pairs.sort((a, b) => b.volume - a.volume);
    
    const top150 = pairs.slice(0, 150);
    console.log(`Scanning top ${top150.length} altcoins (Rules: StochRSI < 7, RSI < 40)...`);

    const matches = [];
    
    for (let j = 0; j < top150.length; j++) {
      const pair = top150[j];
      try {
        const klineRes = await fetch(`https://api.kucoin.com/api/v1/market/candles?symbol=${pair.symbol}&type=1day`);
        const klines = await klineRes.json();
        if (klines.code !== "200000" || !klines.data || klines.data.length < 40) continue;
        
        const closes = klines.data.map(bar => parseFloat(bar[2])).reverse();
        const rsiValues = calculateRSI(closes, 14);
        const kValues = calculateStochRSI(rsiValues, 14, 3);
        
        const lastRsi = rsiValues[rsiValues.length - 1];
        const lastK = kValues[kValues.length - 1];
        
        if (lastRsi !== null && lastK !== null && !isNaN(lastRsi) && !isNaN(lastK) && lastRsi < 40 && lastK < 7) {
          matches.push({
            symbol: pair.symbol.replace("-", ""),
            rsi: parseFloat(lastRsi.toFixed(2)),
            stochK: parseFloat(lastK.toFixed(2)),
            price: closes[closes.length - 1],
            volume_24h: pair.volume
          });
        }
      } catch (err) {
        // Skip on error
      }
    }
    
    // Write results to JSON
    const outputData = {
      last_updated: new Date().toISOString(),
      matches_count: matches.length,
      matches: matches
    };
    
    // Ensure parent directory exists
    const dir = path.dirname(resultsPath);
    if (!fs.existsSync(dir)){
      fs.mkdirSync(dir, { recursive: true });
    }
    
    fs.writeFileSync(resultsPath, JSON.stringify(outputData, null, 2));
    console.log(`Scan completed. ${matches.length} matches written to ${resultsPath}`);
    process.exit(0);
  } catch (err) {
    console.error("Error running scan:", err);
    process.exit(1);
  }
}

main();
