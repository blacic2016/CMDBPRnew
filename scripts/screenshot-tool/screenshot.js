const puppeteer = require('puppeteer');

(async () => {
    const url = process.argv[2];
    const outputPath = process.argv[3];

    if (!url || !outputPath) {
        console.error('Usage: node screenshot.js <url> <outputPath>');
        process.exit(1);
    }

    console.log(`Starting screenshot for: ${url} -> ${outputPath}`);
    let browser;
    try {
        browser = await puppeteer.launch({
            headless: 'new',
            args: [
                '--no-sandbox', 
                '--disable-setuid-sandbox', 
                '--disable-dev-shm-usage', 
                '--disable-gpu',
                '--ignore-certificate-errors'
            ]
        });
        const page = await browser.newPage();
        await page.setViewport({ width: 1280, height: 800 });
        
        // Add user agent to bypass simple bot checks
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');
        
        // Set timeout to 20 seconds
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 20000 });
        
        // Wait a bit for any dynamic animations
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        await page.screenshot({ path: outputPath, type: 'png' });
        console.log('SUCCESS');
        process.exit(0);
    } catch (error) {
        console.error('ERROR:', error.message);
        process.exit(1);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
})();
