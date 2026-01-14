# Vendor Dependencies

## MatthiasMullie\Minify (Optional)

Das Modul funktioniert mit einem einfachen Fallback-Minifier, aber für bessere Ergebnisse (besonders bei modernen CSS-Features wie CSS Custom Properties, CSS Parts, etc.) wird die Bibliothek **MatthiasMullie\Minify** empfohlen.

### Installation via Composer (Empfohlen)

Falls Composer im Projekt verfügbar ist:

```bash
cd /site/modules/ProcesswireStudio
composer require matthiasmullie/minify
```

### Manuelle Installation

1. Lade die Bibliothek von GitHub herunter:
   - https://github.com/matthiasmullie/minify
   - https://github.com/matthiasmullie/path-converter (Dependency)

2. Erstelle folgende Verzeichnisstruktur:
   ```
   vendor/
   ├── matthiasmullie/
   │   ├── minify/
   │   │   └── src/
   │   └── path-converter/
   │       └── src/
   └── autoload.php
   ```

3. Erstelle eine einfache `autoload.php`:
   ```php
   <?php
   require_once __DIR__ . '/matthiasmullie/minify/src/Minify.php';
   require_once __DIR__ . '/matthiasmullie/minify/src/CSS.php';
   require_once __DIR__ . '/matthiasmullie/minify/src/JS.php';
   require_once __DIR__ . '/matthiasmullie/path-converter/src/Converter.php';
   ```

### Vorteile der Bibliothek

- ✅ Unterstützt moderne CSS-Features (Custom Properties, CSS Parts, etc.)
- ✅ Bessere JavaScript-Minifizierung
- ✅ Verarbeitet @import Statements in CSS
- ✅ Aktiv gepflegt und getestet

### Fallback-Modus

Falls die Bibliothek nicht verfügbar ist, verwendet das Modul einen einfachen Fallback-Minifier, der:
- Kommentare entfernt
- Whitespace reduziert
- Grundlegende Optimierungen durchführt

Der Fallback funktioniert für die meisten Fälle, unterstützt aber nicht alle modernen CSS-Features.
