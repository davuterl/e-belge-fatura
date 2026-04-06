/**
 * ============================================================
 * DASHBOARD GRAFİK VE İSTATİSTİK (dashboard.js)
 * ============================================================
 * Chart.js kütüphanesi ile aylık alış/satış grafikleri çizer.
 * API'den gelen verileri istatistik kartlarına yerleştirir.
 * 
 * Kullanılan Kütüphane: Chart.js v4.4
 * - Canvas tabanlı, responsive grafik kütüphanesi
 * - CDN üzerinden yüklenir (kurulum gerektirmez)
 * - Çizgi, çubuk, pasta gibi birçok grafik türünü destekler
 * ============================================================
 */

// Sayfa yüklendiğinde dashboard verilerini getir
document.addEventListener('DOMContentLoaded', function() {
    dashboardYukle();
});

/**
 * Dashboard verilerini API'den çeker ve UI'ı günceller.
 * İstatistik kartları, grafikler ve son faturalar tablosu doldurulur.
 */
async function dashboardYukle() {
    // API'den istatistik verilerini al
    const sonuc = await apiGet('api/istatistik.php');
    
    if (!sonuc || !sonuc.basarili) {
        toastGoster('Dashboard verileri yüklenemedi', 'error');
        return;
    }

    const veri = sonuc.veri;

    // ============================================================
    // 1. İSTATİSTİK KARTLARINI GÜNCELLE
    // ============================================================
    
    // Satış toplamı
    document.getElementById('stat-satis').textContent = paraFormatla(veri.toplam_satis);
    document.getElementById('stat-satis-sayi').textContent = 
        (veri.fatura_sayilari?.satis || 0) + ' fatura';
    
    // Alış toplamı
    document.getElementById('stat-alis').textContent = paraFormatla(veri.toplam_alis);
    document.getElementById('stat-alis-sayi').textContent = 
        (veri.fatura_sayilari?.alis || 0) + ' fatura';
    
    // Net bakiye (Satış - Alış)
    const net = veri.toplam_satis - veri.toplam_alis;
    document.getElementById('stat-net').textContent = paraFormatla(net);
    const netEl = document.getElementById('stat-toplam-sayi');
    netEl.textContent = net >= 0 ? '↑ Pozitif bakiye' : '↓ Negatif bakiye';
    netEl.className = 'stat-change ' + (net >= 0 ? 'positive' : 'negative');
    
    // Toplam KDV
    document.getElementById('stat-kdv').textContent = paraFormatla(veri.toplam_kdv);
    const toplamFatura = (veri.fatura_sayilari?.satis || 0) + (veri.fatura_sayilari?.alis || 0);
    document.getElementById('stat-durum-sayi').textContent = toplamFatura + ' toplam fatura';

    // ============================================================
    // 2. AYLIK ALIŞ/SATIŞ GRAFİĞİ (Çubuk Grafik)
    // ============================================================
    
    const aylikCtx = document.getElementById('aylik-grafik');
    if (aylikCtx) {
        new Chart(aylikCtx, {
            type: 'bar',  // Çubuk grafik türü
            data: {
                // X ekseni etiketleri (ay adları)
                labels: veri.aylik_veri.map(a => a.ay),
                datasets: [
                    {
                        label: 'Satış',
                        data: veri.aylik_veri.map(a => a.satis),
                        backgroundColor: 'rgba(0, 184, 148, 0.6)',   // Yeşil
                        borderColor: 'rgba(0, 184, 148, 1)',
                        borderWidth: 2,
                        borderRadius: 6,       // Yuvarlatılmış köşeler
                        borderSkipped: false
                    },
                    {
                        label: 'Alış',
                        data: veri.aylik_veri.map(a => a.alis),
                        backgroundColor: 'rgba(225, 112, 85, 0.6)',  // Kırmızı
                        borderColor: 'rgba(225, 112, 85, 1)',
                        borderWidth: 2,
                        borderRadius: 6,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,              // Ekran boyutuna uyum
                maintainAspectRatio: false,     // Container boyutunu kullan
                interaction: {
                    intersect: false,
                    mode: 'index'              // Aynı X değerindeki tüm set'leri göster
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#A0A0B8',  // Legend metin rengi
                            font: { family: 'Inter', size: 12 },
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 14, 23, 0.9)',
                        titleFont: { family: 'Inter' },
                        bodyFont: { family: 'Inter' },
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            // Tooltip'te tutarları formatlı göster
                            label: function(ctx) {
                                return ctx.dataset.label + ': ' + paraFormatla(ctx.raw);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(108, 92, 231, 0.06)' },
                        ticks: { color: '#6C6C80', font: { family: 'Inter', size: 11 } }
                    },
                    y: {
                        grid: { color: 'rgba(108, 92, 231, 0.06)' },
                        ticks: {
                            color: '#6C6C80',
                            font: { family: 'Inter', size: 11 },
                            callback: function(val) {
                                // Y ekseni değerlerini kısa formatta göster
                                if (val >= 1000000) return (val/1000000).toFixed(1) + 'M';
                                if (val >= 1000) return (val/1000).toFixed(0) + 'K';
                                return val;
                            }
                        }
                    }
                }
            }
        });
    }

    // ============================================================
    // 3. BELGE TÜRÜ DAĞILIMI (Pasta Grafik)
    // ============================================================
    
    const belgeCtx = document.getElementById('belge-grafik');
    if (belgeCtx) {
        const belgeler = veri.belge_dagilimi || [];
        // Veri yoksa varsayılan göster
        const belgeLabels = belgeler.length > 0 
            ? belgeler.map(b => b.belge_turu === 'e-fatura' ? 'e-Fatura' : 'e-Arşiv')
            : ['Henüz fatura yok'];
        const belgeData = belgeler.length > 0 
            ? belgeler.map(b => b.sayi)
            : [1];

        new Chart(belgeCtx, {
            type: 'doughnut',  // Halka grafik (ortası boş pasta)
            data: {
                labels: belgeLabels,
                datasets: [{
                    data: belgeData,
                    backgroundColor: [
                        'rgba(108, 92, 231, 0.7)',   // Mor - e-Fatura
                        'rgba(0, 206, 201, 0.7)',     // Turkuaz - e-Arşiv
                    ],
                    borderColor: [
                        'rgba(108, 92, 231, 1)',
                        'rgba(0, 206, 201, 1)',
                    ],
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',  // İç boşluk oranı
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#A0A0B8',
                            font: { family: 'Inter', size: 12 },
                            usePointStyle: true,
                            padding: 20
                        }
                    }
                }
            }
        });
    }

    // ============================================================
    // 4. SON FATURALAR TABLOSU
    // ============================================================
    
    const tbody = document.getElementById('son-faturalar-body');
    if (tbody) {
        if (!veri.son_faturalar || veri.son_faturalar.length === 0) {
            // Fatura yoksa boş durum göster
            tbody.innerHTML = `
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon">📄</div>
                            <h3>Henüz fatura bulunmuyor</h3>
                            <p>Yeni bir fatura ekleyerek başlayın</p>
                            <a href="sayfalar/fatura_ekle.php" class="btn btn-primary btn-sm">
                                + Yeni Fatura Ekle
                            </a>
                        </div>
                    </td>
                </tr>`;
        } else {
            // Faturaları tabloya yerleştir
            tbody.innerHTML = veri.son_faturalar.map(f => `
                <tr>
                    <td><strong>${f.fatura_no}</strong></td>
                    <td>${turBadge(f.fatura_turu)}</td>
                    <td>${belgeBadge(f.belge_turu)}</td>
                    <td>${tarihFormatla(f.tarih)}</td>
                    <td>${f.firma_adi}</td>
                    <td class="fw-bold">${paraFormatla(f.genel_toplam)}</td>
                    <td>${durumBadge(f.durum)}</td>
                    <td>
                        <a href="sayfalar/fatura_detay.php?id=${f.id}" 
                           class="btn btn-outline btn-sm btn-icon" title="Detay">
                            →
                        </a>
                    </td>
                </tr>
            `).join('');
        }
    }
}
