<?php
/**
 * ============================================================
 * VERİTABANI KURULUM SCRİPTİ (db_setup.php)
 * ============================================================
 * 
 * Bu script, uygulamanın ihtiyaç duyduğu SQLite veritabanını
 * ve tablolarını oluşturur. İlk çalıştırmada otomatik olarak
 * çağrılır.
 * 
 * SQLite Avantajları:
 * - Kurulum gerektirmez (PHP ile birlikte gelir)
 * - Tek dosya olarak saklanır (taşınabilir)
 * - SQL standardına uygun sorguları destekler
 * ============================================================
 */

// Yapılandırma dosyasını dahil et
require_once __DIR__ . '/config.php';

/**
 * Veritabanı tablolarını oluşturur.
 * Eğer tablolar zaten mevcutsa, hata vermez (IF NOT EXISTS).
 */
function veritabaniOlustur(): void {
    // Veritabanı bağlantısını al
    $pdo = veritabaniBaglantisi();
    
    // ============================================================
    // FATURALAR TABLOSU
    // ============================================================
    // Ana fatura bilgilerini saklayan tablo.
    // Her fatura bir satır olarak kaydedilir.
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS faturalar (
            id INTEGER PRIMARY KEY AUTOINCREMENT,       -- Otomatik artan benzersiz kimlik
            fatura_no TEXT UNIQUE NOT NULL,              -- Benzersiz fatura numarası (örn: SFA2024000000001)
            fatura_turu TEXT NOT NULL,                   -- Fatura türü: "alis" veya "satis"
            belge_turu TEXT DEFAULT "e-fatura",          -- Belge türü: "e-fatura" veya "e-arsiv"
            tarih DATE NOT NULL,                        -- Fatura tarihi (YYYY-MM-DD)
            vade_tarihi DATE,                           -- Vade tarihi (opsiyonel)
            firma_adi TEXT NOT NULL,                     -- Karşı firma adı
            firma_vkn TEXT,                             -- Vergi Kimlik Numarası (10 veya 11 hane)
            firma_adres TEXT,                           -- Firma adresi
            ara_toplam REAL DEFAULT 0,                  -- KDV hariç toplam tutar
            kdv_toplam REAL DEFAULT 0,                  -- Toplam KDV tutarı
            genel_toplam REAL DEFAULT 0,                -- KDV dahil genel toplam
            durum TEXT DEFAULT "taslak",                -- Fatura durumu: taslak, onaylandi, iptal
            notlar TEXT,                                -- Ek notlar (opsiyonel)
            olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,  -- Kayıt oluşturma zamanı
            guncelleme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP  -- Son güncelleme zamanı
        )
    ');
    
    // ============================================================
    // FATURA KALEMLERİ TABLOSU
    // ============================================================
    // Bir faturaya ait ürün/hizmet kalemlerini saklayan tablo.
    // Her faturanın birden çok kalemi olabilir (1:N ilişki).
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS fatura_kalemleri (
            id INTEGER PRIMARY KEY AUTOINCREMENT,       -- Otomatik artan benzersiz kimlik
            fatura_id INTEGER NOT NULL,                 -- Bağlı olduğu faturanın ID değeri
            urun_adi TEXT NOT NULL,                     -- Ürün veya hizmet adı
            miktar REAL NOT NULL DEFAULT 1,             -- Miktar (adet, kg, lt vb.)
            birim TEXT DEFAULT "Adet",                  -- Birim türü
            birim_fiyat REAL NOT NULL DEFAULT 0,        -- Birim fiyatı (KDV hariç)
            kdv_orani REAL DEFAULT 20,                  -- KDV oranı (%, 1-10-20)
            kdv_tutari REAL DEFAULT 0,                  -- Hesaplanan KDV tutarı
            toplam_tutar REAL DEFAULT 0,                -- Kalem toplam tutarı (KDV dahil)
            -- Foreign Key: fatura silindiğinde kalemleri de sil (CASCADE)
            FOREIGN KEY (fatura_id) REFERENCES faturalar(id) ON DELETE CASCADE
        )
    ');
    
    // ============================================================
    // İNDEKSLER
    // ============================================================
    // Sık yapılan sorguları hızlandırmak için indeksler oluştur
    
    // Fatura türüne göre filtreleme için indeks
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_fatura_turu ON faturalar(fatura_turu)');
    
    // Tarihe göre sıralama için indeks
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_fatura_tarih ON faturalar(tarih)');
    
    // Fatura kalemleri için fatura_id indeksi (JOIN performansı)
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_kalem_fatura ON fatura_kalemleri(fatura_id)');
    
    // Fatura durumuna göre filtreleme için indeks
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_fatura_durum ON faturalar(durum)');
}

/**
 * Veritabanının mevcut olup olmadığını kontrol eder.
 * Yoksa oluşturur.
 * 
 * @return bool Veritabanı hazır mı?
 */
function veritabaniKontrol(): bool {
    try {
        // Veritabanı dosyasının varlığını kontrol et
        if (!file_exists(DB_PATH)) {
            // Veritabanı dosyası yok, oluştur
            veritabaniOlustur();
            return true;
        }
        
        // Veritabanı var ama tabloları kontrol et
        $pdo = veritabaniBaglantisi();
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='faturalar'");
        
        if (!$stmt->fetch()) {
            // Tablo yok, oluştur
            veritabaniOlustur();
        }
        
        return true;
    } catch (Exception $e) {
        error_log('Veritabanı kontrol hatası: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// SCRIPT DOĞRUDAN ÇALIŞTIRILIRSA
// ============================================================
// Bu dosya doğrudan tarayıcıdan açılırsa veritabanını oluştur
if (basename($_SERVER['PHP_SELF']) === 'db_setup.php') {
    veritabaniOlustur();
    echo '<h2>✅ Veritabanı başarıyla oluşturuldu!</h2>';
    echo '<p>Veritabanı dosyası: ' . DB_PATH . '</p>';
    echo '<p><a href="index.php">Ana Sayfaya Git →</a></p>';
}
