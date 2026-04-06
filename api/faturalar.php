<?php
/**
 * ============================================================
 * FATURA API ENDPOINTLERİ (api/faturalar.php)
 * ============================================================
 * CRUD işlemleri: GET, POST, PUT, DELETE
 * JavaScript tarafından Fetch API ile çağrılır.
 * ============================================================
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_setup.php';
veritabaniKontrol();

// CORS başlıkları
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':    handleGet();    break;
        case 'POST':   handlePost();   break;
        case 'PUT':    handlePut();    break;
        case 'DELETE': handleDelete(); break;
        default: jsonYanit(['hata' => 'Geçersiz HTTP metodu'], 405);
    }
} catch (Exception $e) {
    jsonYanit(['hata' => 'Sunucu hatası: ' . $e->getMessage()], 500);
}

// ============================================================
// GET - Fatura Listesi veya Tek Fatura
// ============================================================
function handleGet(): void {
    $pdo = veritabaniBaglantisi();
    
    // Tek fatura detayı
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM faturalar WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        $fatura = $stmt->fetch();
        if (!$fatura) { jsonYanit(['hata' => 'Fatura bulunamadı'], 404); return; }
        
        // Kalemleri getir
        $stmt = $pdo->prepare('SELECT * FROM fatura_kalemleri WHERE fatura_id = ? ORDER BY id');
        $stmt->execute([(int)$_GET['id']]);
        $fatura['kalemler'] = $stmt->fetchAll();
        
        jsonYanit(['basarili' => true, 'veri' => $fatura]);
        return;
    }
    
    // Fatura listesi - filtreler
    $tur = $_GET['tur'] ?? '';
    $arama = $_GET['arama'] ?? '';
    $durum = $_GET['durum'] ?? '';
    $baslangic = $_GET['baslangic'] ?? '';
    $bitis = $_GET['bitis'] ?? '';
    $sayfa = max(1, (int)($_GET['sayfa'] ?? 1));
    
    $where = []; $params = [];
    
    if (!empty($tur) && in_array($tur, ['alis', 'satis'])) {
        $where[] = 'fatura_turu = ?'; $params[] = $tur;
    }
    if (!empty($durum)) { $where[] = 'durum = ?'; $params[] = $durum; }
    if (!empty($baslangic)) { $where[] = 'tarih >= ?'; $params[] = $baslangic; }
    if (!empty($bitis)) { $where[] = 'tarih <= ?'; $params[] = $bitis; }
    if (!empty($arama)) {
        $where[] = '(firma_adi LIKE ? OR fatura_no LIKE ?)';
        $params[] = '%'.$arama.'%'; $params[] = '%'.$arama.'%';
    }
    
    $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Toplam kayıt
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM faturalar $whereSQL");
    $stmt->execute($params);
    $toplamKayit = (int)$stmt->fetchColumn();
    
    $limit = SAYFA_BASINA_KAYIT;
    $offset = ($sayfa - 1) * $limit;
    $toplamSayfa = max(1, ceil($toplamKayit / $limit));
    
    // Faturaları getir
    $params[] = $limit; $params[] = $offset;
    $stmt = $pdo->prepare("SELECT * FROM faturalar $whereSQL ORDER BY tarih DESC, id DESC LIMIT ? OFFSET ?");
    $stmt->execute($params);
    
    jsonYanit([
        'basarili' => true,
        'veri' => $stmt->fetchAll(),
        'sayfalama' => [
            'mevcut_sayfa' => $sayfa,
            'toplam_sayfa' => (int)$toplamSayfa,
            'toplam_kayit' => $toplamKayit,
            'sayfa_basina' => $limit
        ]
    ]);
}

// ============================================================
// POST - Yeni Fatura Oluştur
// ============================================================
function handlePost(): void {
    $pdo = veritabaniBaglantisi();
    $girdi = json_decode(file_get_contents('php://input'), true);
    
    // Zorunlu alan kontrolü
    if (empty($girdi['fatura_turu']) || empty($girdi['tarih']) || empty($girdi['firma_adi'])) {
        jsonYanit(['hata' => 'Fatura türü, tarih ve firma adı zorunludur'], 400); return;
    }
    if (empty($girdi['kalemler']) || !is_array($girdi['kalemler'])) {
        jsonYanit(['hata' => 'En az bir fatura kalemi gereklidir'], 400); return;
    }
    
    $faturaNo = faturaNoUret($girdi['fatura_turu']);
    
    // Toplam hesapla
    $araToplam = 0; $kdvToplam = 0;
    foreach ($girdi['kalemler'] as $k) {
        $t = (float)($k['miktar'] ?? 0) * (float)($k['birim_fiyat'] ?? 0);
        $araToplam += $t;
        $kdvToplam += $t * ((float)($k['kdv_orani'] ?? 20) / 100);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Faturayı kaydet
        $stmt = $pdo->prepare('INSERT INTO faturalar (fatura_no, fatura_turu, belge_turu, tarih, vade_tarihi, firma_adi, firma_vkn, firma_adres, ara_toplam, kdv_toplam, genel_toplam, durum, notlar) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$faturaNo, $girdi['fatura_turu'], $girdi['belge_turu'] ?? 'e-fatura', $girdi['tarih'], $girdi['vade_tarihi'] ?? null, $girdi['firma_adi'], $girdi['firma_vkn'] ?? null, $girdi['firma_adres'] ?? null, $araToplam, $kdvToplam, $araToplam + $kdvToplam, $girdi['durum'] ?? 'taslak', $girdi['notlar'] ?? null]);
        
        $faturaId = $pdo->lastInsertId();
        
        // Kalemleri kaydet
        $stmt = $pdo->prepare('INSERT INTO fatura_kalemleri (fatura_id, urun_adi, miktar, birim, birim_fiyat, kdv_orani, kdv_tutari, toplam_tutar) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($girdi['kalemler'] as $k) {
            $m = (float)($k['miktar'] ?? 1);
            $bf = (float)($k['birim_fiyat'] ?? 0);
            $ko = (float)($k['kdv_orani'] ?? 20);
            $kt = $m * $bf;
            $kd = $kt * ($ko / 100);
            $stmt->execute([$faturaId, $k['urun_adi'] ?? 'Belirtilmemiş', $m, $k['birim'] ?? 'Adet', $bf, $ko, $kd, $kt + $kd]);
        }
        
        $pdo->commit();
        jsonYanit(['basarili' => true, 'mesaj' => 'Fatura başarıyla oluşturuldu', 'veri' => ['id' => (int)$faturaId, 'fatura_no' => $faturaNo]], 201);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonYanit(['hata' => 'Fatura oluşturulamadı: ' . $e->getMessage()], 500);
    }
}

// ============================================================
// PUT - Fatura Güncelle
// ============================================================
function handlePut(): void {
    $pdo = veritabaniBaglantisi();
    $girdi = json_decode(file_get_contents('php://input'), true);
    
    if (empty($girdi['id'])) { jsonYanit(['hata' => 'Fatura ID gereklidir'], 400); return; }
    $faturaId = (int)$girdi['id'];
    
    $stmt = $pdo->prepare('SELECT id FROM faturalar WHERE id = ?');
    $stmt->execute([$faturaId]);
    if (!$stmt->fetch()) { jsonYanit(['hata' => 'Fatura bulunamadı'], 404); return; }
    
    // Sadece durum güncelleme
    if (!empty($girdi['sadece_durum'])) {
        $stmt = $pdo->prepare('UPDATE faturalar SET durum = ?, guncelleme_tarihi = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$girdi['durum'], $faturaId]);
        jsonYanit(['basarili' => true, 'mesaj' => 'Fatura durumu güncellendi']);
        return;
    }
    
    // Tam güncelleme
    $araToplam = 0; $kdvToplam = 0;
    if (!empty($girdi['kalemler'])) {
        foreach ($girdi['kalemler'] as $k) {
            $t = (float)($k['miktar'] ?? 0) * (float)($k['birim_fiyat'] ?? 0);
            $araToplam += $t;
            $kdvToplam += $t * ((float)($k['kdv_orani'] ?? 20) / 100);
        }
    }
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE faturalar SET belge_turu=?, tarih=?, vade_tarihi=?, firma_adi=?, firma_vkn=?, firma_adres=?, ara_toplam=?, kdv_toplam=?, genel_toplam=?, durum=?, notlar=?, guncelleme_tarihi=CURRENT_TIMESTAMP WHERE id=?');
        $stmt->execute([$girdi['belge_turu'] ?? 'e-fatura', $girdi['tarih'], $girdi['vade_tarihi'] ?? null, $girdi['firma_adi'], $girdi['firma_vkn'] ?? null, $girdi['firma_adres'] ?? null, $araToplam, $kdvToplam, $araToplam + $kdvToplam, $girdi['durum'] ?? 'taslak', $girdi['notlar'] ?? null, $faturaId]);
        
        // Eski kalemleri sil, yenileri ekle
        $pdo->prepare('DELETE FROM fatura_kalemleri WHERE fatura_id = ?')->execute([$faturaId]);
        
        if (!empty($girdi['kalemler'])) {
            $stmt = $pdo->prepare('INSERT INTO fatura_kalemleri (fatura_id, urun_adi, miktar, birim, birim_fiyat, kdv_orani, kdv_tutari, toplam_tutar) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($girdi['kalemler'] as $k) {
                $m = (float)($k['miktar'] ?? 1); $bf = (float)($k['birim_fiyat'] ?? 0);
                $ko = (float)($k['kdv_orani'] ?? 20); $kt = $m * $bf; $kd = $kt * ($ko / 100);
                $stmt->execute([$faturaId, $k['urun_adi'] ?? 'Belirtilmemiş', $m, $k['birim'] ?? 'Adet', $bf, $ko, $kd, $kt + $kd]);
            }
        }
        $pdo->commit();
        jsonYanit(['basarili' => true, 'mesaj' => 'Fatura güncellendi']);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonYanit(['hata' => 'Güncelleme hatası: ' . $e->getMessage()], 500);
    }
}

// ============================================================
// DELETE - Fatura Sil
// ============================================================
function handleDelete(): void {
    $pdo = veritabaniBaglantisi();
    $id = $_GET['id'] ?? null;
    if (!$id) {
        $girdi = json_decode(file_get_contents('php://input'), true);
        $id = $girdi['id'] ?? null;
    }
    if (!$id) { jsonYanit(['hata' => 'Fatura ID gereklidir'], 400); return; }
    
    $stmt = $pdo->prepare('SELECT fatura_no FROM faturalar WHERE id = ?');
    $stmt->execute([(int)$id]);
    $fatura = $stmt->fetch();
    if (!$fatura) { jsonYanit(['hata' => 'Fatura bulunamadı'], 404); return; }
    
    $pdo->prepare('DELETE FROM faturalar WHERE id = ?')->execute([(int)$id]);
    jsonYanit(['basarili' => true, 'mesaj' => $fatura['fatura_no'] . ' numaralı fatura silindi']);
}
