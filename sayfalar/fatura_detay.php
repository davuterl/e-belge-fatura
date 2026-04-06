<?php
/**
 * ============================================================
 * FATURA DETAY SAYFASI (sayfalar/fatura_detay.php)
 * ============================================================
 * Seçilen faturanın tüm bilgilerini ve kalemlerini gösterir.
 * Durum güncelleme ve silme işlemleri bu sayfadan yapılabilir.
 * ============================================================
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_setup.php';
veritabaniKontrol();

// URL'den fatura ID'sini al
$faturaId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura Detay - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app-layout">
    <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')">
        <i data-lucide="menu"></i>
    </button>

    <!-- Sol Menü -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon"><i data-lucide="file-text"></i></div>
            <div><h1>e-Belge</h1><span class="brand-sub">Fatura Yönetim Sistemi</span></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Ana Menü</div>
                <a href="../index.php" class="nav-link"><i data-lucide="layout-dashboard" class="nav-icon"></i> Dashboard</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Faturalar</div>
                <a href="satis_faturalari.php" class="nav-link"><i data-lucide="trending-up" class="nav-icon"></i> Satış Faturaları</a>
                <a href="alis_faturalari.php" class="nav-link"><i data-lucide="trending-down" class="nav-icon"></i> Alış Faturaları</a>
                <a href="fatura_ekle.php" class="nav-link"><i data-lucide="plus-circle" class="nav-icon"></i> Yeni Fatura</a>
            </div>
        </nav>
        <div class="sidebar-footer"><?= APP_NAME ?> v<?= APP_VERSION ?></div>
    </aside>

    <!-- Ana İçerik -->
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title" id="detay-baslik">Fatura Detayı</h1>
                <p class="page-subtitle" id="detay-alt-baslik">Yükleniyor...</p>
            </div>
            <div class="d-flex gap-1" id="detay-aksiyonlar">
                <!-- JavaScript ile doldurulacak -->
            </div>
        </div>

        <!-- Fatura Bilgileri -->
        <div class="card mb-3">
            <div class="card-header">
                <h2 class="card-title">Fatura Bilgileri</h2>
                <div id="detay-durum"></div>
            </div>
            <div class="invoice-header" id="fatura-bilgileri">
                <div style="text-align:center;padding:40px;grid-column:1/-1">
                    <div class="loading-spinner"></div>
                    <p class="mt-1 text-muted">Fatura bilgileri yükleniyor...</p>
                </div>
            </div>
        </div>

        <!-- Fatura Kalemleri -->
        <div class="card mb-3">
            <div class="card-header">
                <h2 class="card-title">Fatura Kalemleri</h2>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Ürün / Hizmet</th>
                            <th>Miktar</th>
                            <th>Birim Fiyat</th>
                            <th>KDV Oranı</th>
                            <th>KDV Tutarı</th>
                            <th>Toplam</th>
                        </tr>
                    </thead>
                    <tbody id="kalem-listesi">
                        <tr><td colspan="7" class="text-center text-muted" style="padding:30px">Yükleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
            <!-- Toplamlar -->
            <div class="invoice-totals" id="fatura-toplamlar" style="display:none">
                <div class="totals-table">
                    <div class="total-row">
                        <span>Ara Toplam:</span>
                        <span id="detay-ara-toplam">0,00 ₺</span>
                    </div>
                    <div class="total-row">
                        <span>KDV Toplam:</span>
                        <span id="detay-kdv-toplam">0,00 ₺</span>
                    </div>
                    <div class="total-row">
                        <span>Genel Toplam:</span>
                        <span id="detay-genel-toplam">0,00 ₺</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notlar -->
        <div class="card" id="notlar-card" style="display:none">
            <div class="card-header">
                <h2 class="card-title">Notlar</h2>
            </div>
            <p id="fatura-notlar" style="font-size:14px;color:var(--text-secondary);line-height:1.8"></p>
        </div>
    </main>
</div>

<!-- Silme Onay Modal -->
<div class="modal-overlay" id="sil-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Fatura Sil</h2>
            <button class="modal-close" onclick="modalKapat('sil-modal')">&times;</button>
        </div>
        <p style="color:var(--text-secondary)">Bu faturayı silmek istediğinize emin misiniz? Bu işlem geri alınamaz.</p>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="modalKapat('sil-modal')">İptal</button>
            <button class="btn btn-danger" id="btn-sil-onayla" onclick="faturaDetaySil()">Evet, Sil</button>
        </div>
    </div>
</div>

<div class="toast-container" id="toast-container"></div>

<script src="../js/app.js"></script>
<script src="../js/fatura.js"></script>
<script>
    // Fatura ID'si (PHP'den JavaScript'e aktarılır)
    const FATURA_ID = <?= $faturaId ?>;

    // Sayfa yüklendiğinde fatura detayını getir
    document.addEventListener('DOMContentLoaded', function() {
        lucide.createIcons();
        if (FATURA_ID > 0) {
            faturaDetayYukle();
        } else {
            document.getElementById('fatura-bilgileri').innerHTML = 
                '<div class="empty-state" style="grid-column:1/-1"><div class="empty-icon">⚠️</div><h3>Geçersiz fatura ID</h3></div>';
        }
    });

    /**
     * Fatura detay verilerini API'den çeker ve sayfaya yerleştirir.
     */
    async function faturaDetayYukle() {
        const sonuc = await apiGet(`../api/faturalar.php?id=${FATURA_ID}`);
        
        if (!sonuc || !sonuc.basarili) {
            document.getElementById('fatura-bilgileri').innerHTML = 
                '<div class="empty-state" style="grid-column:1/-1"><div class="empty-icon">❌</div><h3>Fatura bulunamadı</h3></div>';
            return;
        }

        const f = sonuc.veri;

        // Sayfa başlığı
        document.getElementById('detay-baslik').textContent = f.fatura_no;
        document.getElementById('detay-alt-baslik').textContent = 
            (f.fatura_turu === 'satis' ? 'Satış Faturası' : 'Alış Faturası') + ' • ' + tarihFormatla(f.tarih);

        // Durum badge
        document.getElementById('detay-durum').innerHTML = durumBadge(f.durum);

        // Aksiyon butonları
        const turSayfa = f.fatura_turu === 'satis' ? 'satis_faturalari.php' : 'alis_faturalari.php';
        let aksiyonHTML = `<a href="${turSayfa}" class="btn btn-outline btn-sm">← Listeye Dön</a>`;
        aksiyonHTML += `<a href="fatura_ekle.php?id=${f.id}" class="btn btn-primary btn-sm">✏ Düzenle</a>`;
        
        if (f.durum === 'taslak') {
            aksiyonHTML += `<button onclick="durumGuncelle(${f.id},'onaylandi')" class="btn btn-success btn-sm">✓ Onayla</button>`;
        }
        if (f.durum !== 'iptal') {
            aksiyonHTML += `<button onclick="durumGuncelle(${f.id},'iptal')" class="btn btn-outline btn-sm" style="color:var(--danger)">✕ İptal Et</button>`;
        }
        aksiyonHTML += `<button onclick="modalGoster('sil-modal')" class="btn btn-danger btn-sm">🗑 Sil</button>`;
        document.getElementById('detay-aksiyonlar').innerHTML = aksiyonHTML;

        // Fatura bilgileri grid
        document.getElementById('fatura-bilgileri').innerHTML = `
            <div class="invoice-info-group">
                <div class="invoice-info-row"><span class="invoice-info-label">Fatura No:</span><span class="invoice-info-value">${f.fatura_no}</span></div>
                <div class="invoice-info-row"><span class="invoice-info-label">Fatura Türü:</span><span class="invoice-info-value">${f.fatura_turu === 'satis' ? 'Satış' : 'Alış'}</span></div>
                <div class="invoice-info-row"><span class="invoice-info-label">Belge Türü:</span><span class="invoice-info-value">${belgeBadge(f.belge_turu)}</span></div>
                <div class="invoice-info-row"><span class="invoice-info-label">Tarih:</span><span class="invoice-info-value">${tarihFormatla(f.tarih)}</span></div>
                <div class="invoice-info-row"><span class="invoice-info-label">Vade Tarihi:</span><span class="invoice-info-value">${tarihFormatla(f.vade_tarihi)}</span></div>
            </div>
            <div class="invoice-info-group">
                <div class="invoice-info-row"><span class="invoice-info-label">Firma Adı:</span><span class="invoice-info-value">${f.firma_adi}</span></div>
                <div class="invoice-info-row"><span class="invoice-info-label">VKN/TCKN:</span><span class="invoice-info-value">${f.firma_vkn || '-'}</span></div>
                <div class="invoice-info-row"><span class="invoice-info-label">Adres:</span><span class="invoice-info-value">${f.firma_adres || '-'}</span></div>
                <div class="invoice-info-row"><span class="invoice-info-label">Durum:</span><span class="invoice-info-value">${durumBadge(f.durum)}</span></div>
            </div>
        `;

        // Kalem listesi
        const kalemler = f.kalemler || [];
        if (kalemler.length > 0) {
            document.getElementById('kalem-listesi').innerHTML = kalemler.map((k, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td><strong>${k.urun_adi}</strong></td>
                    <td>${k.miktar} ${k.birim || 'Adet'}</td>
                    <td>${paraFormatla(k.birim_fiyat)}</td>
                    <td>%${k.kdv_orani}</td>
                    <td>${paraFormatla(k.kdv_tutari)}</td>
                    <td class="fw-bold">${paraFormatla(k.toplam_tutar)}</td>
                </tr>
            `).join('');
        } else {
            document.getElementById('kalem-listesi').innerHTML = 
                '<tr><td colspan="7" class="text-center text-muted">Kalem bulunamadı</td></tr>';
        }

        // Toplamlar
        document.getElementById('fatura-toplamlar').style.display = 'flex';
        document.getElementById('detay-ara-toplam').textContent = paraFormatla(f.ara_toplam);
        document.getElementById('detay-kdv-toplam').textContent = paraFormatla(f.kdv_toplam);
        document.getElementById('detay-genel-toplam').textContent = paraFormatla(f.genel_toplam);

        // Notlar
        if (f.notlar) {
            document.getElementById('notlar-card').style.display = 'block';
            document.getElementById('fatura-notlar').textContent = f.notlar;
        }
    }

    /** Detay sayfasından fatura silme */
    async function faturaDetaySil() {
        const sonuc = await apiDelete(`../api/faturalar.php?id=${FATURA_ID}`);
        if (sonuc && sonuc.basarili) {
            toastGoster('Fatura silindi', 'success');
            setTimeout(() => window.location.href = 'satis_faturalari.php', 1000);
        } else {
            toastGoster(sonuc?.hata || 'Silme başarısız', 'error');
        }
        modalKapat('sil-modal');
    }

    /** Durum güncelleme sonrası sayfayı yenile */
    async function durumGuncelle(id, yeniDurum) {
        const sonuc = await apiPut('../api/faturalar.php', { id, durum: yeniDurum, sadece_durum: true });
        if (sonuc && sonuc.basarili) {
            toastGoster('Durum güncellendi', 'success');
            faturaDetayYukle();
        }
    }
</script>
</body>
</html>
