<?php
/**
 * ============================================================
 * FATURA EKLEME / DÜZENLEME SAYFASI (sayfalar/fatura_ekle.php)
 * ============================================================
 * Yeni fatura oluşturma ve mevcut faturayı düzenleme formunu içerir.
 * Dinamik kalem ekleme/silme ve otomatik hesaplama özellikleri
 * JavaScript ile sağlanır.
 * 
 * URL parametreleri:
 * - ?tur=satis|alis  -> Yeni fatura türü
 * - ?id=123          -> Düzenleme modu (mevcut fatura)
 * ============================================================
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_setup.php';
veritabaniKontrol();

// URL parametrelerini al
$duzenleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$varsayilanTur = $_GET['tur'] ?? 'satis';
$baslik = $duzenleId > 0 ? 'Fatura Düzenle' : 'Yeni Fatura Oluştur';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $baslik ?> - <?= APP_NAME ?></title>
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
                <a href="fatura_ekle.php" class="nav-link active"><i data-lucide="plus-circle" class="nav-icon"></i> Yeni Fatura</a>
            </div>
        </nav>
        <div class="sidebar-footer"><?= APP_NAME ?> v<?= APP_VERSION ?></div>
    </aside>

    <!-- Ana İçerik -->
    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title"><?= $baslik ?></h1>
                <p class="page-subtitle">Fatura bilgilerini doldurun ve kalemleri ekleyin</p>
            </div>
        </div>

        <!-- Fatura Formu -->
        <form id="fatura-formu" onsubmit="faturaFormGonder(event)">
            <!-- Gizli ID alanı (düzenleme modunda kullanılır) -->
            <input type="hidden" id="fatura-id" value="<?= $duzenleId ?>">

            <!-- Fatura Başlık Bilgileri -->
            <div class="card mb-3">
                <div class="card-header">
                    <h2 class="card-title">Fatura Bilgileri</h2>
                </div>
                <div class="form-row">
                    <!-- Fatura Türü -->
                    <div class="form-group">
                        <label class="form-label" for="fatura-turu">Fatura Türü *</label>
                        <select class="form-control" id="fatura-turu" required>
                            <option value="satis" <?= $varsayilanTur === 'satis' ? 'selected' : '' ?>>Satış Faturası</option>
                            <option value="alis" <?= $varsayilanTur === 'alis' ? 'selected' : '' ?>>Alış Faturası</option>
                        </select>
                    </div>
                    <!-- Belge Türü -->
                    <div class="form-group">
                        <label class="form-label" for="belge-turu">Belge Türü</label>
                        <select class="form-control" id="belge-turu">
                            <option value="e-fatura">e-Fatura</option>
                            <option value="e-arsiv">e-Arşiv Fatura</option>
                        </select>
                    </div>
                    <!-- Durum -->
                    <div class="form-group">
                        <label class="form-label" for="fatura-durum">Durum</label>
                        <select class="form-control" id="fatura-durum">
                            <option value="taslak">Taslak</option>
                            <option value="onaylandi">Onaylandı</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <!-- Fatura Tarihi -->
                    <div class="form-group">
                        <label class="form-label" for="fatura-tarih">Fatura Tarihi *</label>
                        <input type="date" class="form-control" id="fatura-tarih" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <!-- Vade Tarihi -->
                    <div class="form-group">
                        <label class="form-label" for="vade-tarihi">Vade Tarihi</label>
                        <input type="date" class="form-control" id="vade-tarihi" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                </div>
            </div>

            <!-- Firma Bilgileri -->
            <div class="card mb-3">
                <div class="card-header">
                    <h2 class="card-title">Firma Bilgileri</h2>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="firma-adi">Firma Adı *</label>
                        <input type="text" class="form-control" id="firma-adi" placeholder="Firma adını girin" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="firma-vkn">VKN / TCKN</label>
                        <input type="text" class="form-control" id="firma-vkn" placeholder="Vergi Kimlik No" maxlength="11">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="firma-adres">Adres</label>
                    <textarea class="form-control" id="firma-adres" rows="2" placeholder="Firma adresi"></textarea>
                </div>
            </div>

            <!-- Fatura Kalemleri -->
            <div class="card mb-3">
                <div class="card-header">
                    <h2 class="card-title">Fatura Kalemleri</h2>
                    <button type="button" class="btn btn-primary btn-sm" onclick="kalemEkle()">
                        + Kalem Ekle
                    </button>
                </div>
                
                <!-- Kalem başlıkları -->
                <div class="kalem-baslik">
                    <span>Ürün / Hizmet</span>
                    <span>Miktar</span>
                    <span>Birim Fiyat</span>
                    <span>KDV</span>
                    <span>KDV Tutarı</span>
                    <span>Toplam</span>
                    <span></span>
                </div>

                <!-- Dinamik kalem satırları buraya eklenir -->
                <div id="kalemler-container">
                    <!-- İlk kalem JavaScript ile eklenecek -->
                </div>

                <!-- Toplamlar -->
                <div class="invoice-totals">
                    <div class="totals-table">
                        <div class="total-row">
                            <span>Ara Toplam:</span>
                            <span id="ara-toplam">0,00 ₺</span>
                        </div>
                        <div class="total-row">
                            <span>KDV Toplam:</span>
                            <span id="kdv-toplam">0,00 ₺</span>
                        </div>
                        <div class="total-row">
                            <span>Genel Toplam:</span>
                            <span id="genel-toplam">0,00 ₺</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notlar -->
            <div class="card mb-3">
                <div class="card-header">
                    <h2 class="card-title">Notlar</h2>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <textarea class="form-control" id="fatura-notlar" rows="3" placeholder="Fatura ile ilgili ek notlar..."></textarea>
                </div>
            </div>

            <!-- Form Butonları -->
            <div class="d-flex justify-between" style="flex-wrap:wrap;gap:12px">
                <a href="javascript:history.back()" class="btn btn-outline">← Geri Dön</a>
                <div class="d-flex gap-1">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="save" style="width:16px;height:16px"></i>
                        <?= $duzenleId > 0 ? 'Güncelle' : 'Faturayı Kaydet' ?>
                    </button>
                </div>
            </div>
        </form>
    </main>
</div>

<div class="toast-container" id="toast-container"></div>

<script src="../js/app.js"></script>
<script src="../js/fatura.js"></script>
<script>
    // Düzenleme ID'si (PHP'den JS'e)
    const DUZENLE_ID = <?= $duzenleId ?>;

    document.addEventListener('DOMContentLoaded', async function() {
        lucide.createIcons();

        if (DUZENLE_ID > 0) {
            // Düzenleme modu: Mevcut fatura verilerini yükle
            const sonuc = await apiGet(`../api/faturalar.php?id=${DUZENLE_ID}`);
            if (sonuc && sonuc.basarili) {
                const f = sonuc.veri;
                // Form alanlarını doldur
                document.getElementById('fatura-turu').value = f.fatura_turu;
                document.getElementById('belge-turu').value = f.belge_turu || 'e-fatura';
                document.getElementById('fatura-tarih').value = f.tarih;
                document.getElementById('vade-tarihi').value = f.vade_tarihi || '';
                document.getElementById('firma-adi').value = f.firma_adi;
                document.getElementById('firma-vkn').value = f.firma_vkn || '';
                document.getElementById('firma-adres').value = f.firma_adres || '';
                document.getElementById('fatura-durum').value = f.durum;
                document.getElementById('fatura-notlar').value = f.notlar || '';

                // Kalemleri ekle
                if (f.kalemler && f.kalemler.length > 0) {
                    f.kalemler.forEach(k => {
                        kalemEkle();
                        const satirlar = document.querySelectorAll('.kalem-satir');
                        const sonSatir = satirlar[satirlar.length - 1];
                        sonSatir.querySelector('.kalem-urun').value = k.urun_adi;
                        sonSatir.querySelector('.kalem-miktar').value = k.miktar;
                        sonSatir.querySelector('.kalem-fiyat').value = k.birim_fiyat;
                        sonSatir.querySelector('.kalem-kdv').value = k.kdv_orani;
                    });
                    kalemleriHesapla();
                } else {
                    kalemEkle();
                }
            } else {
                toastGoster('Fatura bulunamadı', 'error');
                kalemEkle();
            }
        } else {
            // Yeni fatura modu: Boş kalem ekle
            kalemEkle();
        }
    });
</script>
</body>
</html>
