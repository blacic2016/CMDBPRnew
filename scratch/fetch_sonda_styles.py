import urllib.request
import re

url = "https://www.sonda.com/ResourcePackages/Sonda2024/assets/dist/css/estilos.css"
req = urllib.request.Request(
    url, 
    headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'}
)

try:
    with urllib.request.urlopen(req, timeout=10) as response:
        css = response.read().decode('utf-8')
        print("Fetched CSS successfully. Length:", len(css))
        
        # Search for color variables or root variables
        vars_match = re.findall(r'--[a-zA-Z0-9_-]+\s*:[^;]+;', css)
        print("CSS variables found (first 30):")
        for v in vars_match[:30]:
            print("  ", v.strip())
            
        # Search for font family
        fonts = re.findall(r'font-family\s*:[^;]+;', css)
        print("Font family references (first 10):")
        for f in set(fonts):
            print("  ", f.strip())
            
except Exception as e:
    print("Error:", e)
