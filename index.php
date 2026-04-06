<?php
/**
 * ============================================================
 * ANA SAYFA / DASHBOARD (index.php)
 * ============================================================
 * Fatura istatistiklerini, grafikleri ve son faturaları gösterir.
 * Chart.js kütüphanesi ile aylık alış/satış grafikleri çizilir.
 * ============================================================
 */

// -- Yapılandırma dosyasını dahil et (veritabanı bağlantısı, sabitler, yardımcı fonksiyonlar) --
require_once __DIR__ . '/config.php';

// -- Veritabanı kurulum dosyasını dahil et (tablo oluşturma fonksiyonları) --
require_once __DIR__ . '/db_setup.php';

// -- Veritabanı tablolarının mevcut olduğunu kontrol et, yoksa oluştur --
veritabaniKontrol();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="e-Belge Fatura Yönetim Sistemi - Alış ve satış faturalarınızı kolayca yönetin">
    <title>Dashboard - <?= APP_NAME ?></title>
    
    <!-- Google Fonts - Inter: Modern ve okunabilir font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons - Hafif ve modern SVG ikon kütüphanesi -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Chart.js - Grafik kütüphanesi (canvas tabanlı, responsive) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Ana stil dosyası -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- ============================================================
     APP-LAYOUT: CSS Grid ile sidebar + içerik düzeni oluşturulur.
     Sol tarafta 260px sidebar, sağ tarafta ana içerik alanı yer alır.
     ============================================================ -->
<div class="app-layout">
    <!-- ============================================================
         SOL MENÜ (Sidebar)
         ============================================================ -->
    <!-- Mobil menü açma butonu -->
    <button class="mobile-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')" id="mobile-toggle-btn">
        <i data-lucide="menu"></i>
    </button>

    <aside class="sidebar" id="main-sidebar">
        <!-- Marka / Logo -->
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i data-lucide="file-text"></i>
            </div>
            <div>
                <h1>e-Belge</h1>
                <span class="brand-sub">Fatura Yönetim Sistemi</span>
            </div>
        </div>

        <!-- Navigasyon Menüsü -->
        <nav class="sidebar-nav">
            <!-- Ana Sayfa Bölümü -->
            <div class="nav-section">
                <div class="nav-section-title">Ana Menü</div>
                <a href="index.php" class="nav-link <?= aktifSayfa('index.php') ?>" id="nav-dashboard">
                    <i data-lucide="layout-dashboard" class="nav-icon"></i>
                    Dashboard
                </a>
            </div>

            <!-- Faturalar Bölümü -->
            <div class="nav-section">
                <div class="nav-section-title">Faturalar</div>
                <a href="sayfalar/satis_faturalari.php" class="nav-link <?= aktifSayfa('satis_faturalari.php') ?>" id="nav-satis">
                    <i data-lucide="trending-up" class="nav-icon"></i>
                    Satış Faturaları
                </a>
                <a href="sayfalar/alis_faturalari.php" class="nav-link <?= aktifSayfa('alis_faturalari.php') ?>" id="nav-alis">
                    <i data-lucide="trending-down" class="nav-icon"></i>
                    Alış Faturaları
                </a>
                <a href="sayfalar/fatura_ekle.php" class="nav-link <?= aktifSayfa('fatura_ekle.php') ?>" id="nav-ekle">
                    <i data-lucide="plus-circle" class="nav-icon"></i>
                    Yeni Fatura
                </a>
            </div>

            <!-- Bilgi Bölümü -->
            <div class="nav-section">
                <div class="nav-section-title">Bilgi</div>
                <a href="#" class="nav-link" onclick="bilgilendirmeGoster()" id="nav-bilgi">
                    <i data-lucide="info" class="nav-icon"></i>
                    e-Belge Bilgi
                </a>
            </div>
        </nav>

        <!-- Sidebar alt bilgi -->
        <div class="sidebar-footer">
            <?= APP_NAME ?> v<?= APP_VERSION ?>
        </div>
    </aside>

    <!-- ============================================================
         ANA İÇERİK
         ============================================================ -->
    <main class="main-content">
        <!-- Sayfa Başlığı: gradient text efektli başlık ve yeni fatura ekleme butonu -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Fatura özet istatistikleri ve grafikler</p>
            </div>
            <!-- Yeni fatura oluşturma sayfasına yönlendiren buton -->
            <a href="sayfalar/fatura_ekle.php" class="btn btn-primary" id="btn-yeni-fatura">
                <i data-lucide="plus"></i>
                Yeni Fatura
            </a>
        </div>

        <!-- ============================================================
             İSTATİSTİK KARTLARI
             4 adet istatistik kartı: Satış, Alış, Net Bakiye, KDV
             dashboard.js tarafından API'den veriler çekilip bu kartlar güncellenir.
             Her kart farklı renk temasına sahiptir (CSS'te .stat-card sınıfları).
             ============================================================ -->
        <div class="stats-grid" id="stats-grid">
            <!-- Satış Toplamı - yeşil tema, trending-up ikonu -->
            <div class="stat-card satis">
                <div class="stat-icon">
                    <i data-lucide="trending-up"></i>
                </div>
                <div class="stat-info">
                    <h3>Toplam Satış</h3>
                    <div class="stat-value" id="stat-satis">0,00 ₺</div>
                    <div class="stat-change positive" id="stat-satis-sayi">0 fatura</div>
                </div>
            </div>
            <!-- Alış Toplamı -->
            <div class="stat-card alis">
                <div class="stat-icon">
                    <i data-lucide="trending-down"></i>
                </div>
                <div class="stat-info">
                    <h3>Toplam Alış</h3>
                    <div class="stat-value" id="stat-alis">0,00 ₺</div>
                    <div class="stat-change negative" id="stat-alis-sayi">0 fatura</div>
                </div>
            </div>
            <!-- Net Bakiye -->
            <div class="stat-card toplam">
                <div class="stat-icon">
                    <i data-lucide="wallet"></i>
                </div>
                <div class="stat-info">
                    <h3>Net Bakiye</h3>
                    <div class="stat-value" id="stat-net">0,00 ₺</div>
                    <div class="stat-change" id="stat-toplam-sayi">Satış - Alış</div>
                </div>
            </div>
            <!-- Toplam KDV -->
            <div class="stat-card kdv">
                <div class="stat-icon">
                    <i data-lucide="percent"></i>
                </div>
                <div class="stat-info">
                    <h3>Toplam KDV</h3>
                    <div class="stat-value" id="stat-kdv">0,00 ₺</div>
                    <div class="stat-change" id="stat-durum-sayi">Tüm faturalar</div>
                </div>
            </div>
        </div>

        <!-- ============================================================
             GRAFİKLER
             Chart.js kütüphanesi ile çizilir. 2 grafik:
             1. Aylık Alış/Satış çubuk grafik (bar chart)
             2. Belge türü dağılımı halka grafik (doughnut chart)
             dashboard.js dosyasında Chart nesneleri oluşturulur.
             ============================================================ -->
        <div class="charts-grid">
            <!-- Aylık Alış/Satış çubuk grafiği - son 6 ayın verilerini gösterir -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Aylık Alış / Satış</h2>
                </div>
                <div class="chart-container">
                    <canvas id="aylik-grafik"></canvas>
                </div>
            </div>
            <!-- Belge Türü Dağılımı -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Belge Türü Dağılımı</h2>
                </div>
                <div class="chart-container">
                    <canvas id="belge-grafik"></canvas>
                </div>
            </div>
        </div>

        <!-- ============================================================
             SON FATURALAR TABLOSU
             En son eklenen 5 faturayı gösterir.
             dashboard.js tarafından API'den çekilip tbody'ye yerleştirilir.
             Her satırda: Fatura No, Tür, Belge, Tarih, Firma, Tutar, Durum
             ============================================================ -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Son Faturalar</h2>
                <a href="sayfalar/satis_faturalari.php" class="btn btn-outline btn-sm">
                    Tümünü Gör <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
                </a>
            </div>
            <div class="table-container">
                <table id="son-faturalar-tablo">
                    <thead>
                        <tr>
                            <th>Fatura No</th>
                            <th>Tür</th>
                            <th>Belge</th>
                            <th>Tarih</th>
                            <th>Firma</th>
                            <th>Tutar</th>
                            <th>Durum</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="son-faturalar-body">
                        <tr>
                            <td colspan="8" class="text-center text-muted" style="padding:40px">
                                <div class="loading-spinner"></div>
                                <p class="mt-1">Yükleniyor...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- ============================================================
     BİLGİLENDİRME MODAL
     Sidebar'daki "e-Belge Bilgi" linkine tıklandığında açılır.
     e-Belge özel entegratörlük hakkında kısa bilgi sunar.
     bilgilendirmeGoster() fonksiyonu ile tetiklenir (app.js).
     ============================================================ -->
<div class="modal-overlay" id="bilgi-modal">
    <div class="modal" style="max-width:640px">
        <div class="modal-header">
            <h2 class="modal-title">e-Belge Özel Entegratörlük Bilgisi</h2>
            <button class="modal-close" onclick="modalKapat('bilgi-modal')">&times;</button>
        </div>
        <div style="font-size:14px;line-height:1.8;color:var(--text-secondary)">
            <h3 style="color:var(--primary-light);margin-bottom:8px">e-Belge Nedir?</h3>
            <p>e-Belge, GİB tarafından yürütülen, ticari belgelerin elektronik ortamda düzenlenmesi, iletilmesi ve saklanmasını kapsayan sistemlerin genel adıdır.</p>
            <h3 style="color:var(--primary-light);margin:16px 0 8px">Özel Entegratörlük</h3>
            <p>GİB tarafından yetkilendirilen firmaların, e-Belge uygulamalarına geçmek isteyen mükelleflere aracılık hizmeti sunmasıdır.</p>
            <h3 style="color:var(--primary-light);margin:16px 0 8px">Başvuru Şartları</h3>
            <ul style="padding-left:20px">
                <li>ISO 27001, ISO 20000, ISO 22301 sertifikaları</li>
                <li>ITIL sertifikalı personel</li>
                <li>7/24 kesintisiz altyapı</li>
                <li>TÜBİTAK Mali Mühür Uyum Raporu</li>
                <li>GİB bağımsız denetim onayı</li>
            </ul>
            <p class="mt-2"><strong>Detaylı bilgi:</strong> <a href="https://ebelge.gib.gov.tr" target="_blank">ebelge.gib.gov.tr</a></p>
        </div>
    </div>
</div>

<!-- Toast Bildirim Container - sağ üst köşede bildirimler burada gösterilir -->
<div class="toast-container" id="toast-container"></div>

<!-- ============================================================
     JAVASCRIPT DOSYALARI
     1. app.js: Ortak fonksiyonlar (API iletişim, toast, modal, formatlama)
     2. dashboard.js: Chart.js grafikleri ve istatistik kartlarını günceller
     Sayfa yüklendiğinde dashboard.js otomatik olarak API'den veri çeker.
     ============================================================ -->
<script src="js/app.js"></script>
<script src="js/dashboard.js"></script>

<script>
    // Lucide ikonlarını başlat
    lucide.createIcons();
</script>
</body>
</html>
