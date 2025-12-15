from playwright.sync_api import sync_playwright

def verify_homepage():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()
        try:
            # Navigate to homepage
            page.goto("http://localhost:8000/index.html")
            
            # Wait for content or at least the header
            page.wait_for_selector('header h1')
            
            # Screenshot homepage
            page.screenshot(path="verification/homepage.png")
            print("Homepage screenshot taken.")
            
            # Navigate to article (mock) - since DB is not connected, it might show empty or error
            # But we can check if structure loads
            page.goto("http://localhost:8000/article.html?id=123")
            page.wait_for_selector('.article-container')
            page.screenshot(path="verification/article.png")
            print("Article screenshot taken.")
            
            # Navigate to auth
            page.goto("http://localhost:8000/auth.html")
            page.wait_for_selector('.auth-container')
            page.screenshot(path="verification/auth.png")
            print("Auth screenshot taken.")

        except Exception as e:
            print(f"Error: {e}")
        finally:
            browser.close()

if __name__ == "__main__":
    verify_homepage()
