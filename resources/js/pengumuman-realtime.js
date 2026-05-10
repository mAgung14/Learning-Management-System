// resources/js/pengumuman-realtime.js

/**
 * Real-time pengumuman listener menggunakan Laravel Echo + Reverb
 * Gunakan file ini di komponen Vue/React atau halaman yang perlu mendengarkan pengumuman baru
 */

export class PengumumanRealtime {
    constructor() {
        this.channel = null;
        this.listeners = [];
    }

    /**
     * Mulai mendengarkan pengumuman baru
     * @param {Function} callback - Callback yang dipanggil saat ada pengumuman baru
     */
    listen(callback) {
        if (!window.Echo) {
            console.error('Laravel Echo belum tersedia');
            return;
        }

        // Subscribe ke channel pengumuman
        this.channel = window.Echo.channel('pengumuman');

        // Listen untuk event PengumumanCreated
        this.channel.listen('.App\\Events\\PengumumanCreated', (data) => {
            console.log('Pengumuman baru diterima:', data);
            callback(data);
        });

        console.log('Mendengarkan pengumuman realtime...');
    }

    /**
     * Berhenti mendengarkan
     */
    stop() {
        if (this.channel) {
            this.channel.stopListening('.App\\Events\\PengumumanCreated');
            window.Echo.leave('pengumuman');
            this.channel = null;
            console.log('Berhenti mendengarkan pengumuman');
        }
    }

    /**
     * Cek status koneksi
     */
    isConnected() {
        return this.channel !== null;
    }
}

// Contoh penggunaan di Vue.js:
/*
// Di komponen Vue
import { PengumumanRealtime } from './pengumuman-realtime.js';

export default {
    data() {
        return {
            realtime: null,
            pengumumanBaru: []
        }
    },

    mounted() {
        this.realtime = new PengumumanRealtime();
        this.realtime.listen((data) => {
            // Tambahkan pengumuman baru ke list
            this.pengumumanBaru.unshift(data);

            // Tampilkan notifikasi
            this.$toast.success(`Pengumuman baru: ${data.judul}`);
        });
    },

    beforeUnmount() {
        if (this.realtime) {
            this.realtime.stop();
        }
    }
}
*/

// Contoh penggunaan di React:
/*
// Di komponen React
import { useEffect, useState } from 'react';
import { PengumumanRealtime } from './pengumuman-realtime.js';

function PengumumanComponent() {
    const [pengumumanBaru, setPengumumanBaru] = useState([]);
    const [realtime, setRealtime] = useState(null);

    useEffect(() => {
        const rt = new PengumumanRealtime();
        setRealtime(rt);

        rt.listen((data) => {
            setPengumumanBaru(prev => [data, ...prev]);
            alert(`Pengumuman baru: ${data.judul}`);
        });

        return () => {
            if (rt) {
                rt.stop();
            }
        };
    }, []);

    return (
        <div>
            <h2>Pengumuman Realtime</h2>
            {pengumumanBaru.map((item, index) => (
                <div key={index} className="pengumuman-item">
                    <h3>{item.judul}</h3>
                    <p>{item.deskripsi}</p>
                    <small>{item.created_at}</small>
                </div>
            ))}
        </div>
    );
}

export default PengumumanComponent;
*/

// Contoh penggunaan vanilla JavaScript:
/*
// Di halaman HTML biasa
document.addEventListener('DOMContentLoaded', function() {
    const realtime = new PengumumanRealtime();

    realtime.listen(function(data) {
        // Tambahkan ke DOM
        const container = document.getElementById('pengumuman-container');
        const div = document.createElement('div');
        div.className = 'pengumuman-item';
        div.innerHTML = `
            <h3>${data.judul}</h3>
            <p>${data.deskripsi}</p>
            <small>${data.created_at}</small>
        `;
        container.insertBefore(div, container.firstChild);

        // Tampilkan notifikasi
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('Pengumuman Baru', {
                body: data.judul,
                icon: '/favicon.ico'
            });
        }
    });

    // Cleanup saat halaman unload
    window.addEventListener('beforeunload', function() {
        realtime.stop();
    });
});
*/