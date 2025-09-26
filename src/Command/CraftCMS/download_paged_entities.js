// This browser automation script downloads entity JSON files from Craft CMS admin interface.
// The current CraftCMSMigrator combines two data inputs, one from these JSONs, and other remaining data from prod DB tables.
// This script to automatizes looping over Dashboard > Entity pages, and downloads full JSONs.
//
// The script uses puppeteer-extra and the stealth plugin to avoid bot detection:
// Install:
//    npm install puppeteer puppeteer-extra puppeteer-extra-plugin-stealth
// Update script:
//    replace www.example.com in script with actual domain
// Run:
//    node download_paged_entities.js
// Usage:
//    The script will start the browser. First manually log in. Second, the script expects you
//    to access the Entry page, and order the list of entries by Date Created ascending.
//    At that time (when entries page is sorted ascending), it will begin downloading files
//    from the current page you opened, until the last page available.
//
// page 1: https://www.example.com/admin/entries?site=siteNHI&source=*&sort=dateCreated-asc
// page 2: https://www.example.com/admin/entries/p2?site=siteNHI&source=*&sort=dateCreated-asc
// page 3: https://www.example.com/admin/entries/p3?site=siteNHI&source=*&sort=dateCreated-asc
// etc.

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

// Tell puppeteer to use the stealth plugin.
puppeteer.use(StealthPlugin());

// Helper to get page number from URL.
function getPageSuffix(url) {
  const match = url.match(/\/admin\/entries(?:\/p(\d+))?/);
  let pageNum = 1;
  if (match && match[1]) {
    pageNum = parseInt(match[1], 10);
  }
  return `_p${pageNum}`;
}

// Wait for the download to appear and rename it.
async function waitAndRenameDownload(downloadPath, pageUrl) {
  const path = require('path');
  const fs = require('fs');
  const suffix = getPageSuffix(pageUrl);
  const originalFile = path.join(downloadPath, 'entries.json');
  const newFile = path.join(downloadPath, `entries${suffix}.json`);

  // Wait for the file to appear (polling).
  for (let i = 0; i < 40; i++) { // Wait up to 20 seconds.
    if (fs.existsSync(originalFile)) {
      fs.renameSync(originalFile, newFile);
      console.log(`Renamed download to: ${newFile}`);
      return;
    }
    await new Promise(resolve => setTimeout(resolve, 500));
  }
  console.warn('Download did not appear in time.');
}

(async () => {
  try {
    console.log('🚀 Launching stealth browser...');
    const browser = await puppeteer.launch({
      headless: false, // Must be false to log in manually
      // Using a real Chrome path can sometimes help, but let Puppeteer's bundled Chromium work first.
      // executablePath: '/Applications/Google Chrome Canary.app/Contents/MacOS/Google Chrome Canary',
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-infobars',
        '--window-position=0,0',
        '--ignore-certifcate-errors',
        '--ignore-certifcate-errors-spki-list',
        '--window-size=1920,1080',
      ],
    });

    const page = await browser.newPage();
    // Output all browser console logs, errors, and warnings to the Node.js CLI
    page.on('console', msg => {
      // Only show log, error, and warning
      if (['log', 'error', 'warning', 'warn'].includes(msg.type())) {
        console.log(`BROWSER ${msg.type().toUpperCase()}:`, msg.text());
      }
    });

    // Set download path.
    const fs = require('fs');
    const path = require('path');
    const downloadPath = path.resolve('./downloaded_entities/');
    fs.mkdirSync(downloadPath, { recursive: true });
    // Set download behavior.
    const client = await page.target().createCDPSession();
    await client.send('Page.setDownloadBehavior', {
      behavior: 'allow',
      downloadPath,
    });

    // Set a realistic viewport and user agent.
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');

    console.log('Navigating to login page, waiting 60sec for login...');
    await page.goto('https://www.example.com/admin/login', {
      waitUntil: 'networkidle2', // Wait for network to be quiet.
      timeout: 60000 // Increase timeout to 60 seconds.
    });

    // --- Manual Login Step ---
    console.log('✅ Page loaded. Please log in manually.');
    console.log('Go visit an entries page -- make sure it is ordered by date created and ascending -- to resume download from there, e.g.s:');
    console.log('- https://www.example.com/admin/entries?site=siteNHI&source=*&sort=dateCreated-asc');
    console.log('- https://www.example.com/admin/entries/p565?site=siteNHI&source=*&sort=dateCreated-asc');

    // Wait until the user is on any valid entries page before starting the loop
    await page.waitForFunction(() => {
      return /\/admin\/entries(\/p\d+)?\?site=siteNHI&source=\*&sort=dateCreated-asc$/.test(location.pathname + location.search);
    }, { timeout: 0 });
    
    // Main pagination loop.
    while (true) {

      // Wait for selector '#content > div.main.element-index > div.elements' to be visible but just with one single class class="elements", not any other classes like "elements busy".
      await page.waitForFunction(() => {
        const el = document.querySelector('#content > div.main.element-index > div.elements');
        return el && el.offsetParent !== null && el.className.trim() === 'elements';
      }, { timeout: 60000 });
      
      // Wait for the next page to load.
      await page.waitForSelector('#count-container > div > div', { timeout: 60000 });
      
      // Wait for the select all checkbox to be present and visible after navigation.
      await page.waitForSelector('#content > div.main.element-index > div.elements > div > table > thead > tr > th.checkbox-cell.selectallcontainer > div', { visible: true, timeout: 10000 });
      await page.evaluate(() => {
        const selectAllCheckboxDiv = document.querySelector('#content > div.main.element-index > div.elements > div > table > thead > tr > th.checkbox-cell.selectallcontainer > div');
        if (selectAllCheckboxDiv) {
          selectAllCheckboxDiv.click();
        }
      });

      // Remove overlay if present before waiting for the export form.
      await page.evaluate(() => {
        const overlay = document.querySelector('div.hud-shade');
        if (overlay) overlay.remove();
      });

      // Wait for the export button to be visible and click it.
      await page.waitForSelector('#export-btn', { visible: true, timeout: 10000 });
      await page.click('#export-btn');

      await page.waitForSelector('form.export-form', { visible: true, timeout: 10000 });
      // Fill out the export form.
      await page.evaluate(() => {
        const exportForm = document.querySelector('form.export-form');
        if (exportForm) {
          const selectElements = exportForm.querySelectorAll('select');

          // Select 'Expanded' in the first select.
          if (selectElements.length > 0) {
            const firstSelect = selectElements[0];
            const targetValue1 = 'craft\\elements\\exporters\\Expanded';
            if (Array.from(firstSelect.options).some(opt => opt.value === targetValue1)) {
              firstSelect.value = targetValue1;
              firstSelect.dispatchEvent(new Event('change'));
            } else {
              console.warn(`Option with value "${targetValue1}" not found in the first select.`);
            }
          } else {
            console.error("First select element not found in the form.");
            return;
          }

          // Select 'JSON' in the second select.
          if (selectElements.length > 1) {
            const secondSelect = selectElements[1];
            const targetValue2 = 'json';
            if (Array.from(secondSelect.options).some(opt => opt.value === targetValue2)) {
              secondSelect.value = targetValue2;
              secondSelect.dispatchEvent(new Event('change'));
            } else {
              console.warn(`Option with value "${targetValue2}" not found in the second select.`);
            }
          } else {
            console.error("Second select element not found in the form.");
            return;
          }

          // Click the submit button.
          const submitButton = exportForm.querySelector('button[type="submit"]');
          if (submitButton) {
            submitButton.click();
          } else {
            console.error("Submit button not found in the form.");
          }
        } else {
          console.error("Export form not found after clicking the export button.");
        }
      });

      // Wait for the download and rename it.
      const pageUrl = page.url();
      await waitAndRenameDownload(downloadPath, pageUrl);

      // Instead of clicking the next page button, get the next page URL and redirect to it -- much simpler beacuse it avoids all the overlays, elements in transition, etc.
      const nextBtn = await page.$('#count-container > div > nav > button.page-link.next-page');
      let nextUrl = null;
      if (nextBtn) {
        // Try to get the href from a parent <a> if present, otherwise construct the URL.
        nextUrl = await page.evaluate(() => {
          const btn = document.querySelector('#count-container > div > nav > button.page-link.next-page');
          // If the button is inside a link, use its href.
          if (btn && btn.parentElement && btn.parentElement.tagName === 'A') {
            return btn.parentElement.href;
          }
          // Otherwise, construct the next page URL.
          const currentUrl = window.location.href;
          const match = currentUrl.match(/\/admin\/entries(?:\/p(\d+))?\?site=siteNHI&source=\*&sort=dateCreated-asc/);
          if (match) {
            let nextPage = 2;
            if (match[1]) {
              nextPage = parseInt(match[1], 10) + 1;
            }
            return currentUrl.replace(/(\/admin\/entries)(?:\/p\d+)?(\?site=siteNHI&source=\*&sort=dateCreated-asc)/, `$1/p${nextPage}$2`);
          }
          return null;
        });
      }
      if (nextUrl) {
        await page.goto(nextUrl, { waitUntil: 'networkidle2' });
      } else {
        break; // No more pages or next button not found in time.
      }

    }

    // Keep the browser open for a bit to see the result, then close.
    console.log('Closing browser in 200 seconds...');
    await new Promise(resolve => setTimeout(resolve, 200000));
    await browser.close();

  } catch (error) {
    console.error('An error occurred:', error);
  }
})();
