<?php
/**
 * ============================================================
 * İSTATİSTİK API (api/istatistik.php)
 * ============================================================
 * Dashboard için özet istatistikleri ve grafik verilerini döndürür.
 * JavaScript (Chart.js) tarafından AJAX ile çağrılır.
 * ============================================================
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_setup.php';
veritabaniKontrol();

header('Content-Type: application/json; charset=UTF-8');

$pdo = veritabaniBaglantisi();

try {
    // -- Genel İstatistikler --
    
    // Toplam satış tutarı
    $stmt = $pdo->query("SELECT COALESCE(SUM(genel_toplam), 0) FROM faturalar WHERE fatura_turu = 'satis'");
    $toplamSatis = (float) $stmt->fetchColumn();

    // Toplam alış tutarı
    $stmt = $pdo->query("SELECT COALESCE(SUM(genel_toplam), 0) FROM faturalar WHERE fatura_turu = 'alis'");
    $toplamAlis = (float) $stmt->fetchColumn();

    // Toplam KDV
    $stmt = $pdo->query("SELECT COALESCE(SUM(kdv_toplam), 0) FROM faturalar");
    $toplamKdv = (float) $stmt->fetchColumn();

    // Fatura sayıları
    $stmt = $pdo->query("SELECT fatura_turu, COUNT(*) as sayi FROM faturalar GROUP BY fatura_turu");
    $sayilar = [];
    while ($row = $stmt->fetch()) {
        $sayilar[$row['fatura_turu']] = (int) $row['sayi'];
    }

    // Durum bazlı sayılar
    $stmt = $pdo->query("SELECT durum, COUNT(*) as sayi FROM faturalar GROUP BY durum");
    $durumlar = [];
    while ($row = $stmt->fetch()) {
        $durumlar[$row['durum']] = (int) $row['sayi'];
    }

    // -- Aylık Grafik Verileri (Son 6 ay) --
    $aylikVeri = [];
    for ($i = 5; $i >= 0; $i--) {
        $ay = date('Y-m', strtotime("-$i months"));
        $ayAdi = ayAdiTR($ay);

        // Satış toplamı
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(genel_toplam), 0) FROM faturalar WHERE fatura_turu = 'satis' AND strftime('%Y-%m', tarih) = ?");
        $stmt->execute([$ay]);
        $satisToplam = (float) $stmt->fetchColumn();

        // Alış toplamı
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(genel_toplam), 0) FROM faturalar WHERE fatura_turu = 'alis' AND strftime('%Y-%m', tarih) = ?");
        $stmt->execute([$ay]);
        $alisToplam = (float) $stmt->fetchColumn();

        $aylikVeri[] = [
            'ay'    => $ayAdi,
            'satis' => $satisToplam,
            'alis'  => $alisToplam
        ];
    }

    // -- Son 5 Fatura --
    $stmt = $pdo->query("SELECT id, fatura_no, fatura_turu, belge_turu, tarih, firma_adi, genel_toplam, durum FROM faturalar ORDER BY tarih DESC, id DESC LIMIT 5");
    $sonFaturalar = $stmt->fetchAll();

    // -- Belge Türü Dağılımı (Pasta grafik için) --
    $stmt = $pdo->query("SELECT belge_turu, COUNT(*) as sayi FROM faturalar GROUP BY belge_turu");
    $belgeDagilimi = [];
    while ($row = $stmt->fetch()) {
        $belgeDagilimi[] = $row;
    }

    // Yanıtı gönder
    jsonYanit([
        'basarili' => true,
        'veri' => [
            'toplam_satis'    => $toplamSatis,
            'toplam_alis'     => $toplamAlis,
            'toplam_kdv'      => $toplamKdv,
            'fatura_sayilari' => $sayilar,
            'durum_sayilari'  => $durumlar,
            'aylik_veri'      => $aylikVeri,
            'son_faturalar'   => $sonFaturalar,
            'belge_dagilimi'  => $belgeDagilimi
        ]
    ]);

} catch (Exception $e) {
    jsonYanit(['hata' => $e->getMessage()], 500);
}

/**
 * Ay numarasını Türkçe ay adına çevirir.
 * Örnek: "2024-01" -> "Oca 2024"
 */
function ayAdiTR(string $ayYil): string {
    $aylar = ['Oca','Şub','Mar','Nis','May','Haz','Tem','Ağu','Eyl','Eki','Kas','Ara'];
    $parts = explode('-', $ayYil);
    $ayIndex = (int)$parts[1] - 1;
    return $aylar[$ayIndex] . ' ' . $parts[0];
}
