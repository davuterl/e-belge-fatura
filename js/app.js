/**
 * ============================================================
 * ANA JAVASCRIPT DOSYASI (app.js)
 * ============================================================
 * Tüm sayfalarda kullanılan ortak fonksiyonları içerir:
 * - API iletişim fonksiyonları (Fetch API)
 * - Toast bildirim sistemi
 * - Modal yönetimi
 * - Sayfalama oluşturucu
 * - Para ve tarih formatlama
 * 
 * Kütüphane: Vanilla JavaScript (harici bağımlılık yok)
 * ============================================================
 */

// ============================================================
// API İLETİŞİM FONKSİYONLARI
// ============================================================

/**
 * API'ye GET isteği gönderir.
 * Fatura listesi ve detay getirmek için kullanılır.
 * 
 * @param {string} url - API endpoint URL'i
 * @returns {Promise<Object>} API yanıtı (JSON)
 */
async function apiGet(url) {
    try {
        const yanit = await fetch(url);
        if (!yanit.ok) throw new Error(`HTTP ${yanit.status}`);
        return await yanit.json();
    } catch (hata) {
        console.error('API GET hatası:', hata);
        toastGoster('Veri yüklenirken hata oluştu', 'error');
        return null;
    }
}

/**
 * API'ye POST isteği gönderir (Yeni kayıt oluşturma).
 * 
 * @param {string} url - API endpoint URL'i
 * @param {Object} veri - Gönderilecek veri
 * @returns {Promise<Object>} API yanıtı
 */
async function apiPost(url, veri) {
    try {
        const yanit = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(veri)
        });
        return await yanit.json();
    } catch (hata) {
        console.error('API POST hatası:', hata);
        toastGoster('İşlem sırasında hata oluştu', 'error');
        return null;
    }
}

/**
 * API'ye PUT isteği gönderir (Güncelleme).
 */
async function apiPut(url, veri) {
    try {
        const yanit = await fetch(url, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(veri)
        });
        return await yanit.json();
    } catch (hata) {
        console.error('API PUT hatası:', hata);
        toastGoster('Güncelleme sırasında hata oluştu', 'error');
        return null;
    }
}

/**
 * API'ye DELETE isteği gönderir (Silme).
 */
async function apiDelete(url) {
    try {
        const yanit = await fetch(url, { method: 'DELETE' });
        return await yanit.json();
    } catch (hata) {
        console.error('API DELETE hatası:', hata);
        toastGoster('Silme sırasında hata oluştu', 'error');
        return null;
    }
}

// ============================================================
// TOAST BİLDİRİM SİSTEMİ
// ============================================================

/**
 * Ekranın sağ üst köşesinde bildirim mesajı gösterir.
 * 3 saniye sonra otomatik olarak kaybolur.
 * 
 * @param {string} mesaj - Gösterilecek mesaj
 * @param {string} tur - Bildirim türü: 'success', 'error', 'warning', 'info'
 */
function toastGoster(mesaj, tur = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    // Toast elementini oluştur
    const toast = document.createElement('div');
    toast.className = `toast ${tur}`;

    // İkon seçimi - türe göre farklı ikon
    const ikonlar = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };

    toast.innerHTML = `
        <span style="font-size:18px;font-weight:bold">${ikonlar[tur] || 'ℹ'}</span>
        <span class="toast-message">${mesaj}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;

    container.appendChild(toast);

    // 3 saniye sonra otomatik kaldır (fade-out animasyonu ile)
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ============================================================
// MODAL YÖNETİMİ
// ============================================================

/**
 * Modal pencereyi gösterir.
 * @param {string} modalId - Modal'ın DOM ID'si
 */
function modalGoster(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        // ESC tuşu ile kapatma
        document.addEventListener('keydown', function handler(e) {
            if (e.key === 'Escape') {
                modalKapat(modalId);
                document.removeEventListener('keydown', handler);
            }
        });
    }
}

/**
 * Modal pencereyi kapatır.
 * @param {string} modalId - Modal'ın DOM ID'si
 */
function modalKapat(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('active');
}

/**
 * Bilgilendirme modalını gösterir (sidebar'dan çağrılır).
 */
function bilgilendirmeGoster() {
    modalGoster('bilgi-modal');
}

// ============================================================
// FORMATLAMA FONKSİYONLARI
// ============================================================

/**
 * Sayıyı Türk Lirası formatında gösterir.
 * Örnek: 1234.56 -> "1.234,56 ₺"
 * 
 * @param {number} tutar - Formatlanacak tutar
 * @returns {string} Formatlanmış tutar
 */
function paraFormatla(tutar) {
    return new Intl.NumberFormat('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(tutar) + ' ₺';
}

/**
 * ISO tarih formatını Türkçe formatına çevirir.
 * Örnek: "2024-01-15" -> "15.01.2024"
 * 
 * @param {string} tarih - ISO formatında tarih
 * @returns {string} Türkçe formatında tarih
 */
function tarihFormatla(tarih) {
    if (!tarih) return '-';
    const d = new Date(tarih);
    return d.toLocaleDateString('tr-TR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

// ============================================================
// SAYFALAMA (Pagination) OLUŞTURUCU
// ============================================================

/**
 * Sayfalama HTML'ini oluşturur ve container'a yerleştirir.
 * 
 * @param {Object} sayfalama - API'den gelen sayfalama bilgisi
 * @param {Function} callback - Sayfa değiştiğinde çağrılacak fonksiyon
 * @param {string} containerId - Sayfalama container'ının DOM ID'si
 */
function sayfalamaOlustur(sayfalama, callback, containerId = 'sayfalama') {
    const container = document.getElementById(containerId);
    if (!container || sayfalama.toplam_sayfa <= 1) {
        if (container) container.innerHTML = '';
        return;
    }

    let html = '<div class="pagination">';
    const mevcut = sayfalama.mevcut_sayfa;
    const toplam = sayfalama.toplam_sayfa;

    // Önceki sayfa butonu
    if (mevcut > 1) {
        html += `<a href="#" onclick="event.preventDefault();${callback.name}(${mevcut - 1})">‹</a>`;
    } else {
        html += `<span class="disabled">‹</span>`;
    }

    // Sayfa numaraları (akıllı aralık gösterimi)
    let baslangic = Math.max(1, mevcut - 2);
    let bitis = Math.min(toplam, mevcut + 2);

    if (baslangic > 1) {
        html += `<a href="#" onclick="event.preventDefault();${callback.name}(1)">1</a>`;
        if (baslangic > 2) html += `<span class="disabled">...</span>`;
    }

    for (let i = baslangic; i <= bitis; i++) {
        if (i === mevcut) {
            html += `<span class="active">${i}</span>`;
        } else {
            html += `<a href="#" onclick="event.preventDefault();${callback.name}(${i})">${i}</a>`;
        }
    }

    if (bitis < toplam) {
        if (bitis < toplam - 1) html += `<span class="disabled">...</span>`;
        html += `<a href="#" onclick="event.preventDefault();${callback.name}(${toplam})">${toplam}</a>`;
    }

    // Sonraki sayfa butonu
    if (mevcut < toplam) {
        html += `<a href="#" onclick="event.preventDefault();${callback.name}(${mevcut + 1})">›</a>`;
    } else {
        html += `<span class="disabled">›</span>`;
    }

    html += '</div>';
    container.innerHTML = html;
}

// ============================================================
// DURUM ETİKETİ (Badge) OLUŞTURUCU
// ============================================================

/**
 * Fatura durumuna göre renklı badge HTML'i oluşturur.
 * 
 * @param {string} durum - Fatura durumu (taslak, onaylandi, iptal)
 * @returns {string} Badge HTML'i
 */
function durumBadge(durum) {
    const durumlar = {
        'taslak':    { sinif: 'badge-warning', metin: 'Taslak' },
        'onaylandi': { sinif: 'badge-success', metin: 'Onaylandı' },
        'iptal':     { sinif: 'badge-danger',  metin: 'İptal' }
    };
    const d = durumlar[durum] || { sinif: 'badge-info', metin: durum };
    return `<span class="badge ${d.sinif}">${d.metin}</span>`;
}

/**
 * Fatura türüne göre badge HTML'i oluşturur.
 * 
 * @param {string} tur - Fatura türü (alis, satis)
 * @returns {string} Badge HTML'i
 */
function turBadge(tur) {
    if (tur === 'satis') {
        return '<span class="badge badge-success">Satış</span>';
    }
    return '<span class="badge badge-danger">Alış</span>';
}

/**
 * Belge türüne göre badge HTML'i oluşturur.
 */
function belgeBadge(tur) {
    if (tur === 'e-arsiv') {
        return '<span class="badge badge-info">e-Arşiv</span>';
    }
    return '<span class="badge badge-primary">e-Fatura</span>';
}

// ============================================================
// ONAY DİALOGU
// ============================================================

/**
 * Kullanıcıdan onay ister (silme işlemleri için).
 * 
 * @param {string} mesaj - Onay mesajı
 * @returns {boolean} Kullanıcı onayladı mı?
 */
function onayIste(mesaj) {
    return confirm(mesaj);
}

// ============================================================
// SAYFA YÜKLEME
// ============================================================

// Sayfa yüklendiğinde sidebar'ın mobilde kapanmasını sağla
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('main-sidebar');
    const toggle = document.getElementById('mobile-toggle-btn');
    if (sidebar && sidebar.classList.contains('open') && 
        !sidebar.contains(e.target) && !toggle?.contains(e.target)) {
        sidebar.classList.remove('open');
    }
});
