<?php
/**
 * ============================================================
 * ALIŞ FATURALARI SAYFASI (sayfalar/alis_faturalari.php)
 * ============================================================
 * Alış faturalarını listeler, filtreleme ve arama yapılabilir.
 * Satış faturaları sayfası ile aynı yapıdadır, sadece tür filtresi farklıdır.
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
    <meta name="description" content="Alış faturalarınızı görüntüleyin ve yönetin">
    <title>Alış Faturaları - <?= APP_NAME ?></title>
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
                <a href="alis_faturalari.php" class="nav-link active"><i data-lucide="trending-down" class="nav-icon"></i> Alış Faturaları</a>
                <a href="fatura_ekle.php" class="nav-link"><i data-lucide="plus-circle" class="nav-icon"></i> Yeni Fatura</a>
            </div>
        </nav>
        <div class="sidebar-footer"><?= APP_NAME ?> v<?= APP_VERSION ?></div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Alış Faturaları</h1>
                <p class="page-subtitle">Aldığınız alış faturalarını görüntüleyin</p>
            </div>
            <a href="fatura_ekle.php?tur=alis" class="btn btn-primary" id="btn-yeni-alis">
                <i data-lucide="plus"></i> Yeni Alış Faturası
            </a>
        </div>

        <!-- Filtre Çubuğu -->
        <div class="card mb-3">
            <div class="filter-bar">
                <div class="search-input">
                    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" class="form-control" id="filtre-arama" placeholder="Firma adı veya fatura no ara..." onkeyup="if(event.key==='Enter')filtreUygula()">
                </div>
                <select class="form-control" id="filtre-durum" onchange="filtreUygula()">
                    <option value="">Tüm Durumlar</option>
                    <option value="taslak">Taslak</option>
                    <option value="onaylandi">Onaylandı</option>
                    <option value="iptal">İptal</option>
                </select>
                <input type="date" class="form-control" id="filtre-baslangic" onchange="filtreUygula()" title="Başlangıç tarihi">
                <input type="date" class="form-control" id="filtre-bitis" onchange="filtreUygula()" title="Bitiş tarihi">
                <button class="btn btn-outline btn-sm" onclick="filtreSifirla()">Sıfırla</button>
            </div>
        </div>

        <!-- Fatura Tablosu -->
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
                    <tbody id="fatura-listesi-body">
                        <tr><td colspan="7" class="text-center" style="padding:40px"><div class="loading-spinner"></div><p class="mt-1">Yükleniyor...</p></td></tr>
                    </tbody>
                </table>
            </div>
            <div id="sayfalama"></div>
        </div>
    </main>
<!-- Toast bildirim container'ı -->
<div class="toast-container" id="toast-container"></div>

<!-- JavaScript Dosyaları:
     app.js   - API iletişim, toast bildirim, modal, formatlama fonksiyonları
     fatura.js - Fatura listeleme, filtreleme, silme, kalem ekleme fonksiyonları
-->
<script src="../js/app.js"></script>
<script src="../js/fatura.js"></script>
<script>
    // Lucide SVG ikonlarını oluştur
    lucide.createIcons();
    
    // Filtre türünü 'alis' olarak ayarla - sadece alış faturaları getirilecek
    // Bu değer API'ye ?tur=alis parametresi olarak gönderilir
    mevcutFiltreler.tur = 'alis';
    
    // İlk sayfadaki faturaları API'den çek ve tabloya yerleştir
    faturalariYukle(1);
</script>
</body>
</html>
