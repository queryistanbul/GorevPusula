# Görev Yönetim Sistemi - PHP/MySQL Versiyonu

Modern, glassmorphism tasarımlı, çok bölümlü görev yönetim sistemi. PHP backend, MySQL database ve React frontend kullanır.

## 🚀 Özellikler

### Genel
- ✨ Modern glassmorphism UI tasarımı
- 🔐 JWT tabanlı güvenli kimlik doğrulama
- 👥 Çok kullanıcılı ve çok bölümlü yapı
- 🔒 Bölümler arası yetkilendirme sistemi
- 📱 Responsive tasarım (web ve mobil uyumlu)

### Görev Yönetimi
- 📋 Liste ve Kanban board görünümleri
- 🎯 Sürükle-bırak ile görev durumu güncelleme
- 🔍 Gelişmiş filtreleme (benim işlerim, bölümüm, durum)
- 📎 Dosya ekleri yükleme/indirme
- 🏷️ Öncelik, durum, konu kategorileri
- 📅 Hedef tamamlanma tarihi
- 👤 Görev sahibi ve istek eden takibi

### Yönetim Paneli (Admin)
- 👥 Kullanıcı yönetimi
- 🏢 Bölüm yönetimi ve yetkilendirme
- ⚙️ Sistem ayarları (öncelikler, durumlar, konular)

## 🛠️ Teknoloji Stack

### Backend
- PHP 8.0+ (native PHP, no framework)
- MySQL veritabanı
- JWT authentication
- PDO (prepared statements)
- File upload handling

### Frontend
- React 18
- Vite (build tool)
- React Router v6
- Axios (HTTP client)
- @hello-pangea/dnd (drag & drop)
- Lucide React (icons)

## 📦 Kurulum

### Gereksinimler
- PHP 8.0 veya üzeri
- MySQL 8.0 veya üzeri
- Apache/Nginx web server
- Node.js (v18 veya üzeri) - Frontend için

### 1. Proje Kurulumu

```bash
cd C:\Users\gurso\.gemini\antigravity\scratch\task-management-system-php
```

### 2. MySQL Veritabanı Kurulumu

MySQL'de yeni bir veritabanı oluşturun:
```sql
CREATE DATABASE task_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Schema'yı içe aktarın:
```bash
mysql -u root -p task_management < database/schema.sql
```

### 3. Backend Kurulumu

`backend/config.php` dosyasını düzenleyin ve ayarlarınızı yapın:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'task_management');
define('DB_USER', 'root');
define('DB_PASSWORD', 'your_mysql_password');

define('JWT_SECRET', 'your-secret-key-change-this-in-production');
define('CORS_ALLOW_ORIGIN', 'http://localhost:3000');
```

**Apache Yapılandırması:**
1. `backend` klasörünü web server root'una taşıyın veya bir virtual host oluşturun
2. `.htaccess` dosyasının çalıştığından emin olun (mod_rewrite aktif olmalı)
3. PHP upload limitlerinizi kontrol edin

**Test:**
```bash
# Health check
curl http://localhost/backend/api/health
```

### 4. Frontend Kurulumu

Orijinal React frontend'i kullanın, sadece API URL'ini güncelleyin.

`frontend/src/services/api.js` dosyasında:
```javascript
const API_URL = 'http://localhost/backend/api';
```

### 5. Dizin İzinleri

Upload klasörüne yazma izni verin:
```bash
chmod 755 backend/uploads
chmod 755 backend/logs
```

## 🔑 İlk Giriş

Varsayılan admin kullanıcısı:
- **Kullanıcı Adı:** `admin`
- **Şifre:** `admin123`

> ⚠️ **Önemli:** Üretim ortamında bu şifreyi mutlaka değiştirin!

## 📁 Proje Yapısı

```
task-management-system-php/
├── backend/
│   ├── .htaccess              # Apache routing
│   ├── index.php              # Main entry point
│   ├── config.php             # Configuration
│   ├── routes.php             # Route definitions
│   ├── controllers/           # API controllers
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── DepartmentController.php
│   │   ├── TaskController.php
│   │   └── ConfigController.php
│   ├── middleware/            # Auth & permissions
│   │   ├── Auth.php
│   │   └── Permissions.php
│   ├── database/              # Database connection
│   │   └── Database.php
│   ├── utils/                 # Helper utilities
│   │   ├── Response.php
│   │   ├── Validator.php
│   │   └── JWT.php
│   ├── uploads/               # File storage
│   └── logs/                  # Log files
├── database/
│   └── schema.sql
└── frontend/                  # React app (separate)
```

## 📝 API Endpoints

### Authentication
- `POST /api/auth/login` - Giriş yap
- `GET /api/auth/me` - Mevcut kullanıcı
- `POST /api/auth/logout` - Çıkış yap

### Tasks
- `GET /api/tasks` - Görevleri listele
- `POST /api/tasks` - Yeni görev
- `GET /api/tasks/:id` - Görev detayı
- `PUT /api/tasks/:id` - Görev güncelle
- `DELETE /api/tasks/:id` - Görev sil
- `POST /api/tasks/:id/attachments` - Dosya yükle
- `DELETE /api/tasks/:id/attachments/:id` - Dosya sil
- `GET /api/tasks/attachments/:id/download` - Dosya indir

### Users (Admin)
- `GET /api/users` - Kullanıcı listesi
- `POST /api/users` - Yeni kullanıcı
- `PUT /api/users/:id` - Kullanıcı güncelle
- `DELETE /api/users/:id` - Kullanıcı sil

### Departments (Admin)
- `GET /api/departments` - Bölüm listesi
- `POST /api/departments` - Yeni bölüm
- `PUT /api/departments/:id` - Bölüm güncelle
- `POST /api/departments/:id/permissions` - Yetki ekle
- `GET /api/departments/:id/permissions` - Yetkileri listele

### Config
- `GET /api/config/priorities` - Öncelikler
- `GET /api/config/statuses` - Durumlar
- `GET /api/config/main-topics` - Ana konular
- `GET /api/config/sub-topics` - Alt konular

## 🔒 Güvenlik

- JWT token tabanlı authentication
- PHP `password_hash()` ve `password_verify()` ile şifre hashing
- PDO prepared statements (SQL injection koruması)
- CORS yapılandırması
- File upload validasyonu
- XSS ve CSRF koruması başlıkları

## 🐛 Troubleshooting

### Backend Çalışmıyor
- PHP versiyonunu kontrol edin (`php -v`)
- Apache mod_rewrite'ın aktif olduğundan emin olun
- MySQL bağlantı bilgilerini kontrol edin
- `backend/logs/` klasöründeki hata loglarını inceleyin

### File Upload Çalışmıyor
- `backend/uploads/` klasörünün yazma izni olduğundan emin olun
- PHP upload limitlerini kontrol edin (`php.ini`)
- `.htaccess` dosyasındaki upload ayarlarını kontrol edin

### CORS Hatası
- `config.php` dosyasında `CORS_ALLOW_ORIGIN` ayarını kontrol edin
- Frontend URL'inin doğru olduğundan emin olun

## 📄 Lisans

Bu proje eğitim ve iç kullanım amaçlıdır.

## 👥 Destek

Sorularınız için:
- GitHub Issues
- İletişim: admin@company.com
