# Real-time Pengumuman dengan Laravel Reverb

Implementasi real-time untuk pengumuman di LMS menggunakan Laravel Reverb.

## Setup Backend

1. Pastikan Reverb sudah terinstall dan dikonfigurasi
2. Jalankan server Reverb:
   ```bash
   php artisan reverb:start --host=127.0.0.1 --port=8080
   ```

3. Konfigurasi environment (.env):
   ```
   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=laravel
   REVERB_APP_KEY=laravel-key
   REVERB_APP_SECRET=laravel-secret
   REVERB_HOST=127.0.0.1
   REVERB_PORT=8080
   REVERB_SCHEME=http

   VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
   VITE_REVERB_HOST="${REVERB_HOST}"
   VITE_REVERB_PORT="${REVERB_PORT}"
   VITE_REVERB_SCHEME="${REVERB_SCHEME}"
   ```

## Penggunaan di Frontend

### Import Class
```javascript
import { PengumumanRealtime } from './app.js';
```

### Contoh Penggunaan di Vue.js
```javascript
export default {
    data() {
        return {
            realtime: null,
            pengumuman: []
        }
    },

    mounted() {
        this.realtime = new PengumumanRealtime();
        this.realtime.listen((data) => {
            this.pengumuman.unshift(data);
            this.$toast.success(`Pengumuman baru: ${data.judul}`);
        });
    },

    beforeUnmount() {
        if (this.realtime) {
            this.realtime.stop();
        }
    }
}
```

### Contoh Penggunaan di React
```javascript
import { useEffect, useState } from 'react';
import { PengumumanRealtime } from './app.js';

function PengumumanComponent() {
    const [pengumuman, setPengumuman] = useState([]);

    useEffect(() => {
        const realtime = new PengumumanRealtime();

        realtime.listen((data) => {
            setPengumuman(prev => [data, ...prev]);
        });

        return () => realtime.stop();
    }, []);

    return (
        <div>
            {pengumuman.map((item, index) => (
                <div key={index}>
                    <h3>{item.judul}</h3>
                    <p>{item.deskripsi}</p>
                </div>
            ))}
        </div>
    );
}
```

### Contoh Penggunaan Vanilla JavaScript
```javascript
document.addEventListener('DOMContentLoaded', function() {
    const realtime = new PengumumanRealtime();

    realtime.listen(function(data) {
        const container = document.getElementById('pengumuman-container');
        const div = document.createElement('div');
        div.innerHTML = `<h3>${data.judul}</h3><p>${data.deskripsi}</p>`;
        container.insertBefore(div, container.firstChild);
    });
});
```

## Event Data Structure

Data yang dikirim saat pengumuman baru:

```javascript
{
    id: 1,
    judul: "Judul Pengumuman",
    deskripsi: "Deskripsi lengkap pengumuman",
    mapel_id: 1,
    anggota_kelas_id: null,
    user_id: 1,
    created_at: "2026-05-09 10:30:00"
}
```

## Testing

Untuk test real-time functionality:

1. Buka dua browser/tab
2. Di satu tab, buka halaman dashboard siswa dengan listener aktif
3. Di tab lain, buat pengumuman baru melalui API atau form guru
4. Pengumuman baru harus muncul secara real-time di tab pertama

## Troubleshooting

- Pastikan Reverb server sedang berjalan
- Cek console browser untuk error koneksi WebSocket
- Pastikan environment variables sudah benar
- Verifikasi bahwa channel `pengumuman` dapat diakses (public channel)