<?php
/**
 * ============================================================
 * YAPILANDIRMA DOSYASI (config.php)
 * ============================================================
 * 
 * Bu dosya, uygulamanın temel yapılandırma ayarlarını içerir.
 * Veritabanı bağlantısı, uygulama sabitleri ve yardımcı
 * fonksiyonlar burada tanımlanmaktadır.
 * 
 * Kullanılan Teknoloji: PHP 8+ ve SQLite3
 * ============================================================
 */

// -- Hata raporlamayı etkinleştir (geliştirme ortamı) --
error_reporting(E_ALL);
ini_set('display_errors', 1);

// -- Türkçe karakter desteği için UTF-8 ayarla --
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');

// ============================================================
// UYGULAMA SABİTLERİ
// ============================================================

// Uygulama adı - sayfa başlıklarında kullanılır
define('APP_NAME', 'e-Belge Fatura Yönetim Sistemi');

// Uygulama versiyonu
define('APP_VERSION', '1.0.0');

// Veritabanı dosyasının yolu - SQLite kullandığımız için dosya yolu yeterli
define('DB_PATH', __DIR__ . '/veritabani/efatura.db');

// Sayfa başına gösterilecek kayıt sayısı (sayfalama için)
define('SAYFA_BASINA_KAYIT', 10);

// KDV oranları - Türkiye'deki standart KDV dilimleri
define('KDV_ORANLARI', [1, 10, 20]);

// Fatura durumları
define('FATURA_DURUMLARI', [
    'taslak'    => 'Taslak',
    'onaylandi' => 'Onaylandı',
    'iptal'     => 'İptal Edildi'
]);

// Belge türleri
define('BELGE_TURLERI', [
    'e-fatura' => 'e-Fatura',
    'e-arsiv'  => 'e-Arşiv Fatura'
]);

// ============================================================
// VERİTABANI BAĞLANTISI
// ============================================================

/**
 * Veritabanı bağlantısı oluşturur ve döndürür.
 * 
 * SQLite kullanıyoruz çünkü:
 * - Kurulum gerektirmez (dosya tabanlı)
 * - Taşınabilir (tek dosya)
 * - Küçük-orta ölçekli projeler için idealdir
 * 
 * @return PDO Veritabanı bağlantı nesnesi
 */
function veritabaniBaglantisi(): PDO {
    // -- Statik değişken ile bağlantıyı önbelleğe al (Singleton pattern) --
    // Her çağrıda yeni bağlantı oluşturmak yerine, mevcut bağlantıyı yeniden kullan
    static $pdo = null;
    
    if ($pdo === null) {
        // Veritabanı dizininin var olup olmadığını kontrol et, yoksa oluştur
        $dizin = dirname(DB_PATH);
        if (!is_dir($dizin)) {
            mkdir($dizin, 0777, true); // Dizini ve alt dizinleri oluştur
        }
        
        try {
            // PDO ile SQLite bağlantısı oluştur
            $pdo = new PDO('sqlite:' . DB_PATH);
            
            // Hata modunu exception olarak ayarla - hatalar anında fırlatılır
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Sonuçları ilişkisel dizi (associative array) olarak döndür
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Foreign key desteğini etkinleştir (SQLite'da varsayılan kapalıdır)
            $pdo->exec('PRAGMA foreign_keys = ON');
            
            // WAL modu - eşzamanlı okuma/yazma performansını artırır
            $pdo->exec('PRAGMA journal_mode = WAL');
            
        } catch (PDOException $e) {
            // Bağlantı hatası durumunda kullanıcıya bilgi ver ve çık
            die('Veritabanı bağlantı hatası: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

// ============================================================
// YARDIMCI FONKSİYONLAR
// ============================================================

/**
 * Para birimini Türk Lirası formatında gösterir.
 * Örnek: 1234.56 -> "1.234,56 ₺"
 * 
 * @param float $tutar Formatlanacak tutar
 * @return string Formatlanmış tutar
 */
function paraFormatla(float $tutar): string {
    return number_format($tutar, 2, ',', '.') . ' ₺';
}

/**
 * Tarihi Türkçe formatında gösterir.
 * Örnek: "2024-01-15" -> "15.01.2024"
 * 
 * @param string $tarih ISO formatında tarih (YYYY-MM-DD)
 * @return string Türkçe formatında tarih (DD.MM.YYYY)
 */
function tarihFormatla(string $tarih): string {
    if (empty($tarih)) return '-';
    return date('d.m.Y', strtotime($tarih));
}

/**
 * XSS saldırılarına karşı metin temizleme.
 * HTML özel karakterlerini encode eder.
 * 
 * @param string|null $metin Temizlenecek metin
 * @return string Güvenli metin
 */
function temizle(?string $metin): string {
    if ($metin === null) return '';
    return htmlspecialchars(trim($metin), ENT_QUOTES, 'UTF-8');
}

/**
 * Mevcut sayfayı belirler (aktif menü öğesi için).
 * URL'deki dosya adını kontrol eder.
 * 
 * @param string $sayfa Kontrol edilecek sayfa adı
 * @return string Aktif ise 'active' CSS sınıfını döndürür
 */
function aktifSayfa(string $sayfa): string {
    $mevcutSayfa = basename($_SERVER['PHP_SELF']);
    return ($mevcutSayfa === $sayfa) ? 'active' : '';
}

/**
 * JSON formatında API yanıtı gönderir.
 * AJAX isteklerinde kullanılır.
 * 
 * @param mixed $veri Gönderilecek veri
 * @param int $durum HTTP durum kodu
 */
function jsonYanit($veri, int $durum = 200): void {
    http_response_code($durum);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($veri, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Benzersiz fatura numarası üretir.
 * Format: FAT + Yıl + 9 haneli sıra numarası
 * Örnek: FAT2024000000001
 * 
 * @param string $tur Fatura türü ('alis' veya 'satis')
 * @return string Benzersiz fatura numarası
 */
function faturaNoUret(string $tur): string {
    $pdo = veritabaniBaglantisi();
    
    // Mevcut yılın son fatura numarasını bul
    $onEk = ($tur === 'satis') ? 'SFA' : 'AFA';
    $yil = date('Y');
    $aramaDeseni = $onEk . $yil . '%';
    
    $stmt = $pdo->prepare('SELECT fatura_no FROM faturalar WHERE fatura_no LIKE ? ORDER BY fatura_no DESC LIMIT 1');
    $stmt->execute([$aramaDeseni]);
    $sonFatura = $stmt->fetchColumn();
    
    if ($sonFatura) {
        // Son fatura numarasından sıra numarasını çıkar ve 1 artır
        $siraNo = (int) substr($sonFatura, 7) + 1;
    } else {
        // İlk fatura, sıra numarası 1
        $siraNo = 1;
    }
    
    // 9 haneli sıra numarası ile fatura numarası oluştur
    return $onEk . $yil . str_pad($siraNo, 9, '0', STR_PAD_LEFT);
}
