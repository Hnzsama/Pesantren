const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

async function run() {
    const args = process.argv.slice(2);
    if (args.length < 1) {
        console.error('Usage: node batch_render.cjs <batch_json_path>');
        process.exit(1);
    }

    const batchPath = args[0];
    const batchData = JSON.parse(fs.readFileSync(batchPath, 'utf8'));

    const browser = await puppeteer.launch({
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
        headless: "new"
    });

    try {
        const page = await browser.newPage();
        // Set a larger viewport to ensure we can see the whole element
        await page.setViewport({ width: 1000, height: 800, deviceScaleFactor: 2 });

        for (const item of batchData.items) {
            try {
                await page.setContent(item.html, { waitUntil: 'load' });

                // Capture the specific card element
                const element = await page.$('.card-container');
                if (element) {
                    await element.screenshot({
                        path: item.path,
                        omitBackground: true
                    });
                } else {
                    // Fallback to viewport if element not found
                    await page.screenshot({
                        path: item.path,
                        omitBackground: true
                    });
                }
            } catch (err) {
                console.error(`Failed to render ${item.path}:`, err);
            }
        }
    } finally {
        await browser.close();
    }
}

run().catch(err => {
    console.error(err);
    process.exit(1);
});
