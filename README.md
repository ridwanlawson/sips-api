<p align="center"><img src="https://img.shields.io/badge/Laravel-11-red?logo=laravel" alt="Laravel 11">&nbsp;<img src="https://img.shields.io/badge/PHP-8.2-blue?logo=php" alt="PHP 8.2">&nbsp;<img src="https://img.shields.io/badge/Oracle-19c-red?logo=oracle" alt="Oracle"></p>

<h1 align="center">SIPS MOBILE API</h1>

<p align="center">REST API backend untuk aplikasi mobile SIPS (SKJ Integrated Plantation System). Menyediakan layanan data master, absensi, panen, pengangkutan, dan pelaporan untuk mendukung operasional perkebunan sawit.</p>

---

## Fitur

- **Autentikasi** — Register, login, logout, ganti password (Sanctum token-based)
- **Master Data** — Users, fields, karyawan, kendaraan, business unit, section, gang
- **Absensi** — Pencatatan kehadiran karyawan dengan upload data mobile
- **Panen** — Pencatatan hasil panen (TPH, Ancak) dengan upload mobile
- **Pengangkutan** — Manajemen pengangkutan TBS, SPB, ETD
- **Pelaporan** — Hasil panen, pengangkutan, langsir, LHM, LHA
- **Upload** — Upload absensi, panen, quality, LHM dari file/mobile
- **App Update** — Upload & distribusi APK aplikasi mobile
- **Device Management** — Registrasi & manajemen perangkat
- **API Logging** — Pencatatan seluruh request/response API
- **Oracle Database** — Koneksi ke Oracle 19c via yajra/laravel-oci8

## Tech Stack

- **Laravel 11** — PHP Framework
- **PHP 8.2**
- **Oracle 19c** — Database (via `yajra/laravel-oci8`)
- **Laravel Sanctum** — API Authentication
- **Intervention Image** — Image manipulations
- **Scribe** — API Documentation

## Instalasi

```bash
git clone <repo-url>
cd sips-api
composer install
cp .env.example .env
php artisan key:generate
```

Sesuaikan konfigurasi database di `.env`:

```env
DB_CONNECTION=oracle
DB_HOST=your-oracle-host
DB_PORT=1521
DB_DATABASE=your-schema
DB_USERNAME=your-username
DB_PASSWORD=your-password
```

Jalankan migrasi dan server:

```bash
php artisan migrate
php artisan serve
```

## API Endpoints

### Public
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/register` | Register user baru |
| POST | `/api/login` | Login |
| POST | `/api/app-update/check` | Cek update APK |
| GET | `/api/app/apks` | Daftar versi APK |

### Authenticated (Sanctum)
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/logout` | Logout |
| GET | `/api/user/{id}` | Detail user |
| POST | `/api/change-password` | Ganti password |

**Master Data**
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/master/sips-users` | Data users |
| GET | `/api/master/sips-fields` | Data fields |
| GET | `/api/master/sips-karyawans` | Data karyawan |
| GET | `/api/master/sips-kendaraan` | Data kendaraan |
| GET | `/api/master/sips-businessunit` | Data business unit |
| GET | `/api/master/sips-section` | Data section |
| GET | `/api/master/sips-gang` | Data gang |
| CRUD | `/api/master/maps` | Data maps |

**Transaksi**
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| CRUD | `/api/apps/tphs` | TPH (Tempat Pengumpulan Hasil) |
| CRUD | `/api/apps/ancaks` | Ancak (plot panen) |
| CRUD | `/api/apps/karyawans` | Data karyawan |
| CRUD | `/api/apps/absensis` | Absensi |
| CRUD | `/api/apps/panens` | Panen |
| CRUD | `/api/apps/pengangkutans` | Pengangkutan |

**Upload**
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/api/uploads/attendance` | Upload absensi |
| POST | `/api/uploads/harvesting` | Upload panen |
| POST | `/api/uploads/harvestingquality` | Upload quality |
| POST | `/api/uploads/attendance/mobile` | Upload absensi dari mobile |
| POST | `/api/uploads/harvesting/mobile` | Upload panen dari mobile |
| POST | `/api/uploads/harvestingquality/mobile` | Upload quality dari mobile |
| POST | `/api/uploads/lhm_data/mobile` | Upload LHM dari mobile |

**Reports**
| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/report/hasil-panen` | Laporan hasil panen |
| GET | `/api/report/hasil-pengangkutan` | Laporan hasil pengangkutan |
| GET | `/api/report/hasil-langsir` | Laporan hasil langsir |
| GET | `/api/report/get-lhm` | Data LHM |
| GET | `/api/report/get-lha` | Data LHA |
| GET | `/api/report/get-harvesting` | Data harvesting |

## Development

```bash
# Jalankan server + queue + Vite concurrently
composer run dev
```

## Testing

```bash
php artisan test
```

## License

Proprietary — PT. Sentosa Kalimantan Jaya
