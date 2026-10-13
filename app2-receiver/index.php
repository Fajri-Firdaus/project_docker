<?php

// lokasi menyinpan data yang diterima dari App 1
$dataFile = __DIR__ . '/data.json';

// mengecek data file json
function bacaRekaman($dataFile) {
    return file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
}

// Menerima data dari App 1 (POST JSON)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['nama']) || empty($input['pesan'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Data tidak lengkap']);
        exit;
    }

    error_log("[app2-receiver] Menerima request dari App 1, data dari: " . $input['nama']);

    // validasi input JSON
    $records = bacaRekaman($dataFile);
    $record = [
        'nama' => $input['nama'],
        'pesan' => $input['pesan'],
        'diterima_pada' => date('Y-m-d H:i:s'),
    ];
    $records[] = $record;
    file_put_contents($dataFile, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    error_log("[app2-receiver] Data berhasil disimpan, total rekaman: " . count($records));

    echo json_encode([
        'status' => 'berhasil',
        'message' => 'Data diterima dan disimpan oleh App 2',
        'data' => $record,
    ]);
    exit;
}

// --- Peran 2: endpoint JSON untuk polling oleh JS di halaman ini ---
if (isset($_GET['api']) && $_GET['api'] === 'list') {
    header('Content-Type: application/json');
    $records = array_reverse(bacaRekaman($dataFile)); // terbaru di atas
    echo json_encode([
        'total' => count($records),
        'records' => $records,
    ]);
    exit;
}

// --- Peran 3: halaman HTML yang menampilkan data secara live ---
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>App 2 - Penerima Data</title>
<style>
  * { box-sizing: border-box; }
  body {
    font-family: -apple-system, "Segoe UI", sans-serif;
    max-width: 640px; margin: 60px auto; padding: 0 20px;
    background: #fafafa; color: #1a1a1a; line-height: 1.5;
  }
  h1 { font-size: 1.3rem; margin-bottom: 4px; }
  .subtitle { color: #666; font-size: 0.9rem; margin-top: 0; }
  .status-row { display: flex; align-items: center; gap: 8px; margin: 14px 0; font-size: 0.85rem; color: #555; }
  .dot {
    width: 8px; height: 8px; border-radius: 50%; background: #38a169;
    animation: pulse 1.5s infinite;
  }
  @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.3; } }
  ul { list-style: none; padding: 0; margin-top: 16px; }
  li {
    background: white; border: 1px solid #e2e2e2; border-radius: 8px;
    padding: 12px 14px; margin-bottom: 10px; animation: masuk 0.3s ease;
  }
  @keyframes masuk { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
  li .nama { font-weight: 600; }
  li .waktu { font-size: 0.78rem; color: #888; float: right; }
  li .pesan { margin-top: 4px; color: #333; }
  .empty { color: #999; font-size: 0.9rem; margin-top: 20px; }

  /* Popup notifikasi saat data baru masuk */
  #popup {
    position: fixed; top: 20px; right: 20px;
    background: #2b6cb0; color: white; padding: 12px 18px;
    border-radius: 8px; font-size: 0.9rem; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    opacity: 0; transform: translateY(-10px);
    transition: opacity 0.3s, transform 0.3s; pointer-events: none;
  }
  #popup.show { opacity: 1; transform: translateY(0); }
</style>
</head>
<body>
  <h1>App 2: Penerima Data (live)</h1>
  <p class="subtitle">Halaman ini otomatis memuat ulang data tiap 2 detik, tanpa perlu refresh manual</p>

  <div class="status-row">
    <span class="dot"></span>
    <span id="statusText">Memantau data masuk...</span>
  </div>

  <ul id="list"></ul>
  <p class="empty" id="empty" style="display:none;">Belum ada data masuk. Kirim dari App 1 untuk melihatnya muncul di sini.</p>

  <div id="popup"></div>

  <script>
    let totalSebelumnya = null; // null = belum pernah dimuat sama sekali

    function formatRecord(r) {
      const li = document.createElement('li');
      li.innerHTML = `<span class="waktu">${r.diterima_pada}</span>
                       <span class="nama">${r.nama}</span>
                       <div class="pesan">${r.pesan}</div>`;
      return li;
    }

    function tampilkanPopup(pesan) {
      const popup = document.getElementById('popup');
      popup.textContent = pesan;
      popup.classList.add('show');
      setTimeout(() => popup.classList.remove('show'), 2500);
    }

    async function muatData() {
      try {
        const res = await fetch('/index.php?api=list');
        const data = await res.json();

        // Tampilkan popup hanya jika ini bukan pemuatan pertama, dan ada data baru
        if (totalSebelumnya !== null && data.total > totalSebelumnya) {
          const jumlahBaru = data.total - totalSebelumnya;
          tampilkanPopup(`${jumlahBaru} data baru masuk dari ${data.records[0].nama}`);
        }
        totalSebelumnya = data.total;

        const list = document.getElementById('list');
        const empty = document.getElementById('empty');
        list.innerHTML = '';

        if (data.total === 0) {
          empty.style.display = 'block';
        } else {
          empty.style.display = 'none';
          data.records.forEach(r => list.appendChild(formatRecord(r)));
        }

        document.getElementById('statusText').textContent =
          `Total data diterima: ${data.total}`;
      } catch (err) {
        document.getElementById('statusText').textContent = 'Gagal memuat data';
      }
    }

    muatData();
    setInterval(muatData, 2000);
  </script>
</body>
</html>
