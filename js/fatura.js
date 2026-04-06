/**
 * ============================================================
 * FATURA İŞLEMLERİ (fatura.js)
 * ============================================================
 * Fatura listeleme, filtreleme, silme ve dinamik kalem ekleme
 * işlemlerini yönetir. Fatura ekleme formundaki hesaplamaları
 * otomatik olarak yapar.
 * ============================================================
 */

// ============================================================
// FATURA LİSTELEME
// ============================================================

// Mevcut filtre durumu (sayfa, arama, tarih vb.)
let mevcutFiltreler = {
    tur: '',          // 'alis' veya 'satis'
    arama: '',
    durum: '',
    baslangic: '',
    bitis: '',
    sayfa: 1
};

/**
 * Fatura listesini API'den yükler ve tabloya yerleştirir.
 * Filtreleri URL query parametreleri olarak gönderir.
 * 
 * @param {number} sayfa - Yüklenecek sayfa numarası
 */
async function faturalariYukle(sayfa = 1) {
    mevcutFiltreler.sayfa = sayfa;
    
    // API URL'ini filtreler ile oluştur
    const params = new URLSearchParams();
    if (mevcutFiltreler.tur) params.set('tur', mevcutFiltreler.tur);
    if (mevcutFiltreler.arama) params.set('arama', mevcutFiltreler.arama);
    if (mevcutFiltreler.durum) params.set('durum', mevcutFiltreler.durum);
    if (mevcutFiltreler.baslangic) params.set('baslangic', mevcutFiltreler.baslangic);
    if (mevcutFiltreler.bitis) params.set('bitis', mevcutFiltreler.bitis);
    params.set('sayfa', sayfa);

    // API base URL - sayfa konumuna göre ayarla
    const baseUrl = window.location.pathname.includes('/sayfalar/') 
        ? '../api/faturalar.php' 
        : 'api/faturalar.php';

    const sonuc = await apiGet(`${baseUrl}?${params.toString()}`);
    
    const tbody = document.getElementById('fatura-listesi-body');
    if (!tbody) return;

    if (!sonuc || !sonuc.basarili) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Veriler yüklenemedi</td></tr>';
        return;
    }

    // Tabloya faturaları yerleştir
    if (sonuc.veri.length === 0) {
        tbody.innerHTML = `
            <tr><td colspan="8">
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <h3>Fatura bulunamadı</h3>
                    <p>Filtreleri değiştirin veya yeni fatura ekleyin</p>
                </div>
            </td></tr>`;
    } else {
        tbody.innerHTML = sonuc.veri.map(f => `
            <tr>
                <td><strong>${f.fatura_no}</strong></td>
                <td>${belgeBadge(f.belge_turu)}</td>
                <td>${tarihFormatla(f.tarih)}</td>
                <td>${f.firma_adi}</td>
                <td class="fw-bold">${paraFormatla(f.genel_toplam)}</td>
                <td>${durumBadge(f.durum)}</td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="fatura_detay.php?id=${f.id}" class="btn btn-outline btn-sm btn-icon" title="Detay">👁</a>
                        <a href="fatura_ekle.php?id=${f.id}" class="btn btn-outline btn-sm btn-icon" title="Düzenle">✏</a>
                        <button onclick="faturaSil(${f.id}, '${f.fatura_no}')" class="btn btn-danger btn-sm btn-icon" title="Sil">🗑</button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    // Sayfalama oluştur
    if (sonuc.sayfalama) {
        sayfalamaOlustur(sonuc.sayfalama, faturalariYukle);
    }
}

/**
 * Filtre formundaki değerleri okuyup fatura listesini yeniler.
 */
function filtreUygula() {
    const aramaInput = document.getElementById('filtre-arama');
    const durumSelect = document.getElementById('filtre-durum');
    const baslangicInput = document.getElementById('filtre-baslangic');
    const bitisInput = document.getElementById('filtre-bitis');

    if (aramaInput) mevcutFiltreler.arama = aramaInput.value;
    if (durumSelect) mevcutFiltreler.durum = durumSelect.value;
    if (baslangicInput) mevcutFiltreler.baslangic = baslangicInput.value;
    if (bitisInput) mevcutFiltreler.bitis = bitisInput.value;

    faturalariYukle(1); // Filtreleme sonrası ilk sayfaya dön
}

/**
 * Filtreleri sıfırlar ve listeyi yeniden yükler.
 */
function filtreSifirla() {
    mevcutFiltreler = { ...mevcutFiltreler, arama: '', durum: '', baslangic: '', bitis: '', sayfa: 1 };
    
    // Form elemanlarını temizle
    ['filtre-arama', 'filtre-durum', 'filtre-baslangic', 'filtre-bitis'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });

    faturalariYukle(1);
}

// ============================================================
// FATURA SİLME
// ============================================================

/**
 * Onay sonrası faturayı API üzerinden siler.
 * 
 * @param {number} id - Silinecek faturanın ID'si
 * @param {string} no - Fatura numarası (onay mesajı için)
 */
async function faturaSil(id, no) {
    if (!onayIste(`${no} numaralı faturayı silmek istediğinize emin misiniz?`)) return;

    const baseUrl = window.location.pathname.includes('/sayfalar/') 
        ? '../api/faturalar.php' 
        : 'api/faturalar.php';

    const sonuc = await apiDelete(`${baseUrl}?id=${id}`);
    
    if (sonuc && sonuc.basarili) {
        toastGoster(sonuc.mesaj, 'success');
        faturalariYukle(mevcutFiltreler.sayfa);
    } else {
        toastGoster(sonuc?.hata || 'Silme işlemi başarısız', 'error');
    }
}

// ============================================================
// FATURA DURUMU GÜNCELLEME
// ============================================================

/**
 * Fatura durumunu günceller (onaylama, iptal etme).
 * 
 * @param {number} id - Faturanın ID'si
 * @param {string} yeniDurum - Yeni durum değeri
 */
async function durumGuncelle(id, yeniDurum) {
    const baseUrl = window.location.pathname.includes('/sayfalar/') 
        ? '../api/faturalar.php' 
        : 'api/faturalar.php';

    const sonuc = await apiPut(baseUrl, {
        id: id,
        durum: yeniDurum,
        sadece_durum: true
    });

    if (sonuc && sonuc.basarili) {
        toastGoster('Fatura durumu güncellendi', 'success');
        // Sayfayı yenile
        if (typeof faturalariYukle === 'function') faturalariYukle(mevcutFiltreler.sayfa);
        if (typeof faturaDetayYukle === 'function') faturaDetayYukle();
    }
}

// ============================================================
// DİNAMİK FATURA KALEMİ EKLEME/SİLME
// ============================================================

// Kalem sayacı - benzersiz ID üretmek için
let kalemSayaci = 0;

/**
 * Fatura formuna yeni bir kalem satırı ekler.
 * Her kalem: ürün adı, miktar, birim, birim fiyat, KDV oranı içerir.
 */
function kalemEkle() {
    kalemSayaci++;
    const container = document.getElementById('kalemler-container');
    if (!container) return;

    const satir = document.createElement('div');
    satir.className = 'kalem-satir';
    satir.id = `kalem-${kalemSayaci}`;
    satir.innerHTML = `
        <input type="text" class="form-control kalem-urun" placeholder="Ürün/Hizmet adı" required>
        <input type="number" class="form-control kalem-miktar" value="1" min="0.01" step="0.01" 
               onchange="kalemleriHesapla()" oninput="kalemleriHesapla()">
        <input type="number" class="form-control kalem-fiyat" value="0" min="0" step="0.01"
               placeholder="0,00" onchange="kalemleriHesapla()" oninput="kalemleriHesapla()">
        <select class="form-control kalem-kdv" onchange="kalemleriHesapla()">
            <option value="20">%20</option>
            <option value="10">%10</option>
            <option value="1">%1</option>
        </select>
        <input type="text" class="form-control kalem-kdv-tutar" value="0,00 ₺" readonly 
               style="background:transparent;border-color:transparent;text-align:right">
        <input type="text" class="form-control kalem-toplam" value="0,00 ₺" readonly
               style="background:transparent;border-color:transparent;text-align:right;font-weight:600">
        <button type="button" class="btn-remove" onclick="kalemSil('kalem-${kalemSayaci}')" title="Kalemi sil">×</button>
    `;

    container.appendChild(satir);
    // Yeni eklenen satırın ürün inputuna odaklan
    satir.querySelector('.kalem-urun').focus();
}

/**
 * Belirtilen kalem satırını DOM'dan kaldırır.
 * En az 1 kalem kalmalıdır.
 * 
 * @param {string} kalemId - Silinecek kalem satırının ID'si
 */
function kalemSil(kalemId) {
    const container = document.getElementById('kalemler-container');
    if (!container) return;

    // En az 1 kalem kalmalı
    if (container.children.length <= 1) {
        toastGoster('En az bir kalem olmalıdır', 'warning');
        return;
    }

    const satir = document.getElementById(kalemId);
    if (satir) {
        satir.style.opacity = '0';
        satir.style.transform = 'translateX(-20px)';
        setTimeout(() => {
            satir.remove();
            kalemleriHesapla(); // Toplamları güncelle
        }, 200);
    }
}

// ============================================================
// OTOMATİK HESAPLAMA
// ============================================================

/**
 * Tüm kalem satırlarını tarar ve toplam tutarları hesaplar.
 * Her kalem için: KDV tutarı ve kalem toplamını hesaplar.
 * Alt kısımdaki ara toplam, KDV toplam ve genel toplam güncellenir.
 */
function kalemleriHesapla() {
    const satirlar = document.querySelectorAll('.kalem-satir');
    let araToplam = 0;
    let kdvToplam = 0;

    satirlar.forEach(satir => {
        // Her satırdan değerleri oku
        const miktar = parseFloat(satir.querySelector('.kalem-miktar')?.value) || 0;
        const fiyat = parseFloat(satir.querySelector('.kalem-fiyat')?.value) || 0;
        const kdvOrani = parseFloat(satir.querySelector('.kalem-kdv')?.value) || 20;

        // Hesapla
        const kalemToplam = miktar * fiyat;
        const kdvTutari = kalemToplam * (kdvOrani / 100);

        // Kalem satırındaki alanları güncelle
        const kdvField = satir.querySelector('.kalem-kdv-tutar');
        const toplamField = satir.querySelector('.kalem-toplam');
        if (kdvField) kdvField.value = paraFormatla(kdvTutari);
        if (toplamField) toplamField.value = paraFormatla(kalemToplam + kdvTutari);

        // Genel toplamlara ekle
        araToplam += kalemToplam;
        kdvToplam += kdvTutari;
    });

    // Alt toplam alanlarını güncelle
    const araEl = document.getElementById('ara-toplam');
    const kdvEl = document.getElementById('kdv-toplam');
    const genelEl = document.getElementById('genel-toplam');

    if (araEl) araEl.textContent = paraFormatla(araToplam);
    if (kdvEl) kdvEl.textContent = paraFormatla(kdvToplam);
    if (genelEl) genelEl.textContent = paraFormatla(araToplam + kdvToplam);
}

// ============================================================
// FATURA FORMU GÖNDERME
// ============================================================

/**
 * Fatura formunu doğrular ve API'ye gönderir.
 * Hem yeni fatura oluşturma hem de güncelleme için kullanılır.
 * 
 * @param {Event} event - Form submit event'i
 */
async function faturaFormGonder(event) {
    event.preventDefault(); // Sayfanın yenilenmesini engelle

    // -- Form verilerini topla --
    const faturaId = document.getElementById('fatura-id')?.value;
    const faturaTuru = document.getElementById('fatura-turu')?.value;
    const belgeTuru = document.getElementById('belge-turu')?.value;
    const tarih = document.getElementById('fatura-tarih')?.value;
    const vadeTarihi = document.getElementById('vade-tarihi')?.value;
    const firmaAdi = document.getElementById('firma-adi')?.value;
    const firmaVkn = document.getElementById('firma-vkn')?.value;
    const firmaAdres = document.getElementById('firma-adres')?.value;
    const durum = document.getElementById('fatura-durum')?.value;
    const notlar = document.getElementById('fatura-notlar')?.value;

    // -- Zorunlu alan doğrulama --
    if (!faturaTuru || !tarih || !firmaAdi) {
        toastGoster('Fatura türü, tarih ve firma adı zorunludur', 'warning');
        return;
    }

    // -- Kalem verilerini topla --
    const kalemler = [];
    const satirlar = document.querySelectorAll('.kalem-satir');

    satirlar.forEach(satir => {
        const urunAdi = satir.querySelector('.kalem-urun')?.value;
        const miktar = parseFloat(satir.querySelector('.kalem-miktar')?.value) || 0;
        const birimFiyat = parseFloat(satir.querySelector('.kalem-fiyat')?.value) || 0;
        const kdvOrani = parseFloat(satir.querySelector('.kalem-kdv')?.value) || 20;

        if (urunAdi && miktar > 0 && birimFiyat > 0) {
            kalemler.push({
                urun_adi: urunAdi,
                miktar: miktar,
                birim: 'Adet',
                birim_fiyat: birimFiyat,
                kdv_orani: kdvOrani
            });
        }
    });

    if (kalemler.length === 0) {
        toastGoster('En az bir geçerli kalem ekleyin (ürün adı, miktar ve fiyat)','warning');
        return;
    }

    // -- API isteği oluştur --
    const veri = {
        fatura_turu: faturaTuru,
        belge_turu: belgeTuru || 'e-fatura',
        tarih: tarih,
        vade_tarihi: vadeTarihi || null,
        firma_adi: firmaAdi,
        firma_vkn: firmaVkn || null,
        firma_adres: firmaAdres || null,
        durum: durum || 'taslak',
        notlar: notlar || null,
        kalemler: kalemler
    };

    const baseUrl = window.location.pathname.includes('/sayfalar/') 
        ? '../api/faturalar.php' 
        : 'api/faturalar.php';

    let sonuc;
    // faturaId "0" veya boş ise yeni fatura oluştur, değilse güncelle
    if (faturaId && parseInt(faturaId) > 0) {
        // Güncelleme (PUT)
        veri.id = parseInt(faturaId);
        sonuc = await apiPut(baseUrl, veri);
    } else {
        // Yeni oluşturma (POST)
        sonuc = await apiPost(baseUrl, veri);
    }

    if (sonuc && sonuc.basarili) {
        toastGoster(sonuc.mesaj || 'İşlem başarılı', 'success');
        // 1 saniye sonra detay sayfasına yönlendir
        setTimeout(() => {
            const yeniId = sonuc.veri?.id || faturaId;
            if (yeniId) {
                window.location.href = `fatura_detay.php?id=${yeniId}`;
            } else {
                window.location.href = faturaTuru === 'satis' ? 'satis_faturalari.php' : 'alis_faturalari.php';
            }
        }, 1000);
    } else {
        toastGoster(sonuc?.hata || 'İşlem başarısız oldu', 'error');
    }
}
