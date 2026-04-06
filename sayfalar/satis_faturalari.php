<?php
/**
 * ============================================================
 * SATIŞ FATURALARI SAYFASI (sayfalar/satis_faturalari.php)
 * ============================================================
 * Satış faturalarını listeler, filtreleme ve arama yapılabilir.
 * Fatura ekleme, düzenleme, silme işlemlerine erişim sağlar.
 * ============================================================
 */

// -- Üst dizindeki yapılandırma dosyasını dahil et --
require_once __DIR__ . '/../config.php';

// -- Veritabanı kurulum dosyasını dahil et ve tabloları kontrol et --
require_once __DIR__ . '/../db_setup.php';
veritabaniKontrol();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Satış faturalarınızı görüntüleyin ve yönetin">
    <title>Satış Faturaları - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app-layout">
    <!-- Mobil menü butonu -->
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
                <a href="satis_faturalari.php" class="nav-link active"><i data-lucide="trending-up" class="nav-icon"></i> Satış Faturaları</a>
                <a href="alis_faturalari.php" class="nav-link"><i data-lucide="trending-down" class="nav-icon"></i> Alış Faturaları</a>
                <a href="fatura_ekle.php" class="nav-link"><i data-lucide="plus-circle" class="nav-icon"></i> Yeni Fatura</a>
            </div>
        </nav>
        <div class="sidebar-footer"><?= APP_NAME ?> v<?= APP_VERSION ?></div>
    </aside>

    <!-- ============================================================
         ANA İÇERİK ALANI
         Satış faturalarını listeleyen tablo, filtre çubuğu ve sayfalama içerir.
         Veriler fatura.js dosyasındaki faturalariYukle() fonksiyonu ile
         API'den (api/faturalar.php?tur=satis) çekilir ve tabloya yerleştirilir.
         ============================================================ -->
    <main class="main-content">
        <!-- Sayfa başlığı ve yeni fatura ekleme butonu -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Satış Faturaları</h1>
                <p class="page-subtitle">Düzenlediğiniz satış faturalarını görüntüleyin</p>
            </div>
            <!-- ?tur=satis parametresi ile form otomatik olarak "Satış" seçili gelir -->
            <a href="fatura_ekle.php?tur=satis" class="btn btn-primary" id="btn-yeni-satis">
                <i data-lucide="plus"></i> Yeni Satış Faturası
            </a>
        </div>

        <!-- ============================================================
             FİLTRE ÇUBUĞU
             Arama: Firma adı veya fatura numarasına göre arama yapar
             Durum: Taslak, Onaylandı, İptal durumlarına göre filtreler
             Tarih: Başlangıç ve bitiş tarihi aralığında filtreler
             Her değişiklikte filtreUygula() fonksiyonu çağrılır (fatura.js)
             ============================================================ -->
        <div class="card mb-3">
            <div class="filter-bar">
                <!-- Arama kutusu - Enter tuşu ile de arama yapılabilir -->
                <div class="search-input">
                    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" class="form-control" id="filtre-arama" placeholder="Firma adı veya fatura no ara..." onkeyup="if(event.key==='Enter')filtreUygula()">
                </div>
                <!-- Durum filtresi - değişiklikte otomatik filtrelenir -->
                <select class="form-control" id="filtre-durum" onchange="filtreUygula()">
                    <option value="">Tüm Durumlar</option>
                    <option value="taslak">Taslak</option>
                    <option value="onaylandi">Onaylandı</option>
                    <option value="iptal">İptal</option>
                </select>
                <!-- Tarih aralığı filtreleri -->
                <input type="date" class="form-control" id="filtre-baslangic" onchange="filtreUygula()" title="Başlangıç tarihi">
                <input type="date" class="form-control" id="filtre-bitis" onchange="filtreUygula()" title="Bitiş tarihi">
                <!-- Filtreleri temizleyip tüm faturaları göster -->
                <button class="btn btn-outline btn-sm" onclick="filtreSifirla()">Sıfırla</button>
            </div>
        </div>

        <!-- ============================================================
             FATURA TABLOSU
             Fatura verileri fatura.js'deki faturalariYukle() ile doldurulur.
             tbody#fatura-listesi-body içeriği JavaScript ile dinamik güncellenir.
             Sayfalama bileşeni app.js'deki sayfalamaOlustur() ile oluşturulur.
             ============================================================ -->
        <div class="card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Fatura No</th>
                            <th>Belge Türü</th>
                            <th>Tarih</th>
                            <th>Firma</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <!-- Fatura satırları JavaScript ile buraya eklenir -->
                    <tbody id="fatura-listesi-body">
                        <tr><td colspan="7" class="text-center" style="padding:40px"><div class="loading-spinner"></div><p class="mt-1">Yükleniyor...</p></td></tr>
                    </tbody>
                </table>
            </div>
            <!-- Sayfalama - sayfalamaOlustur() ile dinamik oluşturulur -->
            <div id="sayfalama"></div>
        </div>
    </main>
</div>

<!-- Toast bildirim container'ı -->
<div class="toast-container" id="toast-container"></div>

<!-- JavaScript Dosyaları:
     app.js   - API iletişim, toast bildirim, modal, formatlama fonksiyonları
     fatura.js - Fatura listeleme, filtreleme, silme, kalem ekleme fonksiyonları
-->
<script src="../js/app.js"></script>
<script src="../js/fatura.js"></script>
<script>
    // Lucide SVG ikonlarını HTML'deki data-lucide attribute'larından oluştur
    lucide.createIcons();
    
    // Filtre türünü 'satis' olarak ayarla - sadece satış faturaları getirilecek
    // Bu değer API'ye ?tur=satis parametresi olarak gönderilir
    mevcutFiltreler.tur = 'satis';
    
    // İlk sayfadaki faturaları API'den çek ve tabloya yerleştir
    faturalariYukle(1);
</script>
</body>
</html>
