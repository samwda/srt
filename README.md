# ⏱️ Sam Reading Time (SRT) – WordPress Plugin

**Sam Reading Time (SRT)** is a super lightweight WordPress plugin that calculates and displays the estimated reading time for your posts and pages using a simple shortcode.

---

## ✨ Features

- Estimate reading time based on word count
- Lightweight and easy to use
- Works via `[sam_reading_time]` shortcode
- No settings page or database queries
- Clean output – fully theme-compatible
- Translation-ready and extendable

---

## 🔧 Installation

1. Download the plugin ZIP or clone the repository into your WordPress plugin directory:  
   `/wp-content/plugins/sam-reading-time/`

2. Activate the plugin from the WordPress admin dashboard.

---

## 🧩 Usage

To display the reading time anywhere in your post, simply add the following shortcode:

```
[sam_reading_time]
```

This will output something like:

> Reading Time: 4 minutes

You can place this shortcode in:

- Post or page content
- Custom post types
- Template files (using `do_shortcode()`)

Example for PHP templates:

```php
echo do_shortcode('[sam_reading_time]');
```

---

## 🧠 How Reading Time Is Calculated

- The plugin counts all words in the current post or page content.
- Default reading speed is **200 words per minute**.
- The number of words is divided by 200, then rounded up to the nearest full minute.

For example:

- 765 words / 200 = 3.825
- Rounded = 4 minutes

---

## 🛡 License

Released under the **GPLv2 License**  

---

## 👨‍💻 Author

**Seyyed Ahmadreza Mahjoob**  
🔗 Website: [samwda.ir](https://samwda.ir)  
📦 GitHub: [github.com/samahjoob](https://github.com/samahjoob)

---

## 💬 Feedback & Contributions

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

---

Enjoy fast, clean reading time calculation with **SRT** ⏱✨
