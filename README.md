# 🚗 VatanParvar Yaypan — Avtomaktab Platformasi

Yaypan shahridagi avtomaktab uchun **yo'l harakati qoidalari nazariy imtihoniga onlayn tayyorlanish** platformasi.
**v2.0** — to'liq production-ready, 9 fazada takomillashtirilgan.

---

## ✨ Asosiy xususiyatlar

### 🎨 Dizayn
- **Light Mode** — oq fon + ko'k havorang (`#3B82F6`)
- 9 darajali primary color paleti (50-900)
- 50+ SVG icon (Heroicons style, embedded)
- Animatsiyalar: fade, slide, scale, ripple, confetti, count-up
- Skeleton loaders, toast notifications, modal system
- Empty states, progress bars, tabs, badges

### 🌐 Til
- O'zbek **Lotin** / **Kirill** — to'liq UI o'zgaradi
- 200+ tarjima kalit (`lang/uz_*.php` fayllarda)
- **Avto Lotin → Kirill konvertor** (`uz_latin_to_cyrillic()`)

### 🛡️ Xavfsizlik (FAZA 4)
- **CSRF** himoyasi — barcha POST formalarda
- **Rate limiting** — login (8/15min), register (5/soat), API (60/daq)
- **Audit log** — barcha muhim amallar DB'da
- **Password policy** + strength meter (4 daraja)
- **Secure file upload** — MIME tekshirish, SVG XSS himoyasi
- **Security headers** — CSP, X-Frame, Referrer-Policy, va h.k.
- **Session regenerate** + fingerprint
- **IP banlash** tizimi

### 🎓 Test olish oqimi (FAZA 3) — **MVP**ning eng katta yangiligi
- To'liq UI: savol-javob, taymer, navigator panel
- AJAX **auto-save** har bir javobda
- Klaviatura yorliqlari (1-4, ←→, M)
- Keyingi/oldingi navigatsiya, "belgilash" funksiyasi
- Vaqt tugaganda avtomatik yopish
- `beforeunload` himoyasi (tasodifiy yopilishdan)
- **Animatsiyali natija** sahifasi: countup, confetti (>=80%)
- Har bir savol bo'yicha to'liq tahlil + izohlar
- **Sertifikat PDF** chop etish
- Web Share API orqali natijani ulashish

### 💳 To'lov tizimi (FAZA 7)
| Usul | Status | Fayl |
|---|---|---|
| **Click** | ✓ Production-ready | `includes/payments/click.php` |
| **Payme** | ✓ JSON-RPC 2.0 to'liq | `includes/payments/payme.php` |
| **Manual karta** | ✓ Skrinshot upload | — |
| **Telegram bot** | ✓ Chek skrinshot | `telegram/bot.php` |
| **Invoice/chek** | ✓ HTML, print-friendly, hash bilan tasdiqlash | `includes/payments/invoice.php` |

### 🤖 Telegram Bot (FAZA 8)
Komandalar:
- `/start` — telefon contact orqali ro'yxat/login
- `/tarif` — inline keyboardda tariflar
- `/test` — testni boshlash
- `/aloqa` — kontakt info
- `/yordam`, `/chiqish`

To'lov flow:
1. Foydalanuvchi `/tarif` → tarif tanlash
2. Karta ma'lumotlari beriladi
3. Foydalanuvchi chek skrinshotini yuboradi
4. **Adminga avtomatik xabar** keladi (Approve/Reject inline tugmalari)
5. Tasdiqlangach, foydalanuvchiga avto-xabar va tarif faollashadi

### 📱 PWA (FAZA 5)
- `manifest.json` + Service Worker (`sw.js`)
- **Offline rejim** — sahifalar cache qilingan
- **Add to Home Screen** mobil va desktop'da
- Push notification API tayyor (kelajak uchun)
- Cache strategiyalari: Network First (HTML), Cache First (assets)

### 🎯 Performance
- File-based cache layer (`includes/cache.php`)
- Settings cache (10 daqiqa TTL)
- DB indexlar — 11 ta optimallashtirish
- Lazy loading rasmlar
- Critical CSS inline

### 📊 API (REST/JSON)
```
GET  /api/?action=health         → {"ok":true,"status":"ok"}
GET  /api/?action=tariffs        → tariflar ro'yxati
GET  /api/?action=tickets        → biletlar
GET  /api/?action=stats          → umumiy statistika (5 daq cache)
GET  /api/?action=top-rating     → top 100
GET  /api/?action=blog&page=1    → bloglar
GET  /api/?action=me             → joriy user (sessiya)
POST /api/?action=login          → kirish
POST /api/?action=register       → ro'yxat
POST /api/?action=contact        → aloqa formasi
GET  /api/?action=check-invoice&code=ABC → chek tasdiqlash
```

### ⚙️ Cron job
`cron/daily.php` — har kuni 6 ta vazifa:
1. Tarif muddati tugaganlarni `expired` qilish
2. 3 kun ichida tugaydiganlarni Telegram'da ogohlantirish
3. Eski (24+soat) `in_progress` testlarni yopish
4. 30+ kunlik `info` loglarni o'chirish
5. 7+ kun pending to'lovlarni `rejected`
6. Cache tozalash

```bash
# Crontab namunasi
5 0 * * * /usr/bin/php /var/www/html/cron/daily.php
```

---

## 📂 Loyiha tuzilishi (58 fayl)

```
vatanparvar-yaypan/
├── 🌐 Public sahifalar
│   ├── index.php              # Bosh sahifa (CountUp, carousel, sticky CTA)
│   ├── tariflar.php           # 3 tarif + taqqoslash + FAQ
│   ├── blog.php               # Featured + grid + pagination
│   ├── blog-post.php          # TOC + comments + share + related
│   ├── aloqa.php              # Forma + kontakt + harita
│   ├── login.php              # Eye toggle, demo akkount
│   ├── register.php           # Strength meter, referral
│   ├── forgot.php             # 3-step flow with progress
│   ├── reset.php              # Yangi parol o'rnatish
│   ├── invoice.php            # Chek
│   └── logout.php
│
├── 👤 user/                   # 7 sahifa
│   ├── index.php              # Dashboard + grafik
│   ├── testlar.php            # Bilet tanlash + davom etayotgan
│   ├── test.php               # 🔥 TEST UI (timer, navigator, AJAX)
│   ├── test-result.php        # 🔥 NATIJA (confetti, sertifikat)
│   ├── natijalar.php          # Filter + statistika
│   ├── reyting.php            # Top 100 + my rank
│   ├── profil.php             # Avatar + parol + til
│   ├── tariflar.php           # Click/Payme/Manual to'lov
│   └── referallar.php         # 🔥 YANGI: referal link, stats
│
├── 👑 admin/                  # 10 sahifa
│   ├── index.php              # Dashboard + 2 grafik
│   ├── users.php              # CRUD + block
│   ├── savollar.php           # CRUD + CSV import
│   ├── biletlar.php           # CRUD + auto-generate
│   ├── tariflar.php           # CRUD
│   ├── tolovlar.php           # Filter + status
│   ├── blog.php               # 🔥 YANGI: post CRUD + auto Lotin→Kirill
│   ├── sharhlar.php           # 🔥 YANGI: approve/reject
│   ├── loglar.php             # 🔥 YANGI: filter + CSV export
│   └── sozlamalar.php         # 7 tab + Telegram setWebhook
│
├── 🔧 developer/
│   └── index.php              # DB, cache, logs, API docs
│
├── 🔌 api/                    # 🔥 YANGI: REST API
│   ├── index.php              # 11 endpoints, JSON, CORS
│   ├── click.php              # Click webhook
│   ├── payme.php              # Payme webhook
│   └── check-invoice.php      # Hash orqali chek tekshirish
│
├── ✈️ telegram/                # 🔥 YANGI: Telegram bot
│   ├── bot.php                # Webhook handler (full flow)
│   └── api.php                # API wrapper
│
├── ⏰ cron/
│   └── daily.php              # 🔥 YANGI: 6 vazifa + admin reporting
│
├── 🔧 includes/
│   ├── config.php             # Asosiy sozlamalar
│   ├── database.php           # PDO singleton
│   ├── functions.php          # 🔥 design system + render
│   ├── icons.php              # 🔥 50+ SVG icons
│   ├── auth.php               # 🔥 + reset code flow
│   ├── security.php           # 🔥 YANGI: CSRF, rate, audit
│   ├── cache.php              # 🔥 YANGI: file cache layer
│   └── payments/
│       ├── click.php          # 🔥 ClickPayment class
│       ├── payme.php          # 🔥 PaymePayment (JSON-RPC)
│       └── invoice.php        # 🔥 HTML chek
│
├── 🌍 lang/                    # 🔥 YANGI: alohida til fayllar
│   ├── uz_latin.php
│   └── uz_cyrillic.php
│
├── 🎨 assets/
│   ├── images/
│   │   ├── logo.svg
│   │   ├── banner.svg
│   │   └── icon-512.svg
│   └── uploads/
│
├── 💾 sql/
│   └── database.sql           # 14 jadval + seed + indexlar
│
├── 📱 PWA
│   ├── manifest.json          # 🔥 YANGI
│   └── sw.js                  # 🔥 YANGI Service Worker
│
└── .htaccess
```

---

## 🚀 Ishga tushirish

### 1) DB import
```bash
mysql -u root -p < sql/database.sql
```

### 2) `includes/config.php` da DB sozlash
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'vatanparvar_yaypan');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3) Veb-server
```bash
# PHP built-in
php -S localhost:8000 -t /path/to/vatanparvar-yaypan

# yoki Apache: htdocs/ ga ko'chiring
```

### 4) Cron sozlash (production'da)
```bash
crontab -e
# Qo'shing:
5 0 * * * /usr/bin/php /var/www/html/cron/daily.php
```

### 5) Telegram bot (ixtiyoriy)
1. [@BotFather](https://t.me/BotFather) dan token oling
2. Admin → Sozlamalar → Telegram → tokenni kiriting
3. **"Webhook'ni o'rnatish"** tugmasi (sayt HTTPS bo'lishi kerak)
4. BotFather'ga komandalar ro'yxatini yuboring (admin sahifada ko'rsatilgan)

### 6) Click/Payme integratsiya
Admin → Sozlamalar → To'lov:
- Click: `merchant_id`, `service_id`, `secret_key` to'ldiring
- Payme: `merchant_id`, `secret_key` to'ldiring
- Webhook URL'ni Click/Payme admin paneliga kiriting:
  - Click: `https://your-domain.uz/api/click.php`
  - Payme: `https://your-domain.uz/api/payme.php`

---

## 🔑 Demo akkauntlar

| Rol | Email | Parol |
|---|---|---|
| **Admin** | `admin@vatanparvar.uz` | `admin123` |
| **Developer** | `dev@vatanparvar.uz` | `dev123` |
| **User** | `user@vatanparvar.uz` | `user123` |

---

## 🛠️ Texnik talablar

| Komponent | Versiya |
|---|---|
| PHP | **7.4+** (8.0+ tavsiya etiladi — `match` ishlatilgan) |
| MySQL | 5.7+ yoki MariaDB 10+ |
| Apache/Nginx | har qanday |
| Modullar | PDO, mbstring, fileinfo, curl, gd (avtoload) |

**Composer/npm shart emas** — barcha kod o'z ichida.

---

## 📊 Statistika

| Ko'rsatkich | Qiymat |
|---|---|
| **PHP fayllar** | 50 |
| **Boshqa fayllar** (svg, sql, js, json, md) | 8 |
| **Jami fayllar** | **58** |
| **Tarjima kalitlar** | 200+ |
| **SVG iconlar** | 50+ |
| **DB jadvallar** | 14 |
| **DB indexlar** | 20+ (FK + performance) |
| **API endpoints** | 11 |
| **Sintaksis xatolari** | **0 / 50** ✓ |
| **Runtime test** | Barcha sahifalar HTTP 200 ✓ |

---

## 🎨 Dizayn tokenlari

| Token | Qiymat |
|---|---|
| `--primary` | `#3B82F6` |
| `--primary-100` (light) | `#DBEAFE` |
| `--primary-700` (dark) | `#1D4ED8` |
| `--success` | `#10B981` |
| `--warning` | `#F59E0B` |
| `--danger` | `#EF4444` |
| Spacing scale | `--sp-1` (4px) ... `--sp-20` (80px) |
| Border radius | `--r-xs` (4px) ... `--r-2xl` (24px) |
| Shadow levels | xs, sm, md, lg, xl + primary glow |

---

## 📜 Litsenziya

VatanParvar Yaypan avtomaktabi uchun mahsus ishlab chiqildi.
