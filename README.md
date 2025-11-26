# myZakat

Struktur baru aplikasi memisahkan berkas publik, konfigurasi, dan logika agar lebih rapi serta aman:

```
project-root/
├─ public/            # Hanya aset dan entry point yang diakses browser
│  ├─ index.php       # Front controller sederhana dengan routing query ?page=
│  ├─ login.php       # Halaman login
│  ├─ logout.php      # Proses logout
│  └─ assets/
│     ├─ css/         # login.css, public.css, style.css
│     └─ js/          # keluarga.js
├─ app/
│  ├─ Controllers/    # ceklogin.php, dashboard.php, export_excel.php, family_service.php, lihat_data.php, masyarakat.php
│  └─ Helpers/        # helpers.php
└─ config/
   └─ config.php      # Koneksi database
```

## Cara menggunakan
- Arahkan server web ke folder `public/` sebagai document root.
- Buka `public/login.php` untuk masuk admin, atau `public/index.php?page=masyarakat` untuk tampilan publik.
- Permintaan lain dirutekan melalui `public/index.php?page=...` ke controller terkait (mis. `page=lihat_data`, `page=export_excel`).

Jaga agar kredensial database di `config/config.php` sesuai dengan lingkungan Anda.
