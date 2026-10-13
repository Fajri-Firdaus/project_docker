<?php


// Metode HTTP yang dipakai
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    //mengirim data JOSN
    header('Content-Type: application/json');

    //JSON to Array
    $input = json_decode(file_get_contents('php://input'), true);

    // Validasi input JSON
    if (!$input || empty($input['nama']) || empty($input['pesan'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Data nama/pesan tidak lengkap']);
        exit;
    }

    // Alamat URL App 2
    $app2Url = getenv('APP2_URL') ?: 'http://nginx:8002/index.php';

    error_log("[app1-form] Menerima submit dari browser, meneruskan ke App 2 di $app2Url ...");

    // Inti Komunikasi REST API
    $ch = curl_init($app2Url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($input));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Pengecekan CURL error
    if ($curlError) {
        error_log("[app1-form] Gagal menghubungi App 2: $curlError");
        http_response_code(502);
        echo json_encode(['error' => 'Gagal menghubungi App 2', 'detail' => $curlError]);
        exit;
    }

    // Respon dari App 2
    error_log("[app1-form] Berhasil menerima balasan dari App 2 (HTTP $httpCode)");

    http_response_code($httpCode ?: 200);
    echo $response;
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>App 1 - Form Pengirim</title>
<style>
  * { box-sizing: border-box; }
  body {
    font-family: -apple-system, "Segoe UI", sans-serif;
    max-width: 560px; margin: 60px auto; padding: 0 20px;
    background: #fafafa; color: #1a1a1a; line-height: 1.5;
  }
  h1 { font-size: 1.3rem; margin-bottom: 4px; }
  .subtitle { color: #666; font-size: 0.9rem; margin-top: 0; }
  .diagram {
    display: flex; align-items: center; gap: 10px;
    margin: 20px 0; font-size: 0.82rem; color: #444; flex-wrap: wrap;
  }
  .box { border: 1px solid #ccc; border-radius: 6px; padding: 7px 12px; background: white; }
  label { display: block; margin-top: 14px; font-size: 0.9rem; font-weight: 600; }
  input {
    width: 100%; padding: 10px; margin-top: 6px;
    border: 1px solid #ccc; border-radius: 6px; font-size: 1rem;
  }
  button {
    margin-top: 20px; background: #2b6cb0; color: white; border: none;
    padding: 12px 24px; font-size: 1rem; border-radius: 6px; cursor: pointer;
  }
  button:disabled { background: #aaa; cursor: not-allowed; }
  #status { margin-top: 18px; font-size: 0.95rem; min-height: 24px; }
  pre {
    background: #111; color: #7ee787; padding: 14px; border-radius: 6px;
    overflow-x: auto; font-size: 0.82rem; margin-top: 10px;
  }
  .badge { display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 0.78rem; margin-left: 6px; }
  .ok { background: #c6f6d5; color: #22543d; }
  .wait { background: #feebc8; color: #7b341e; }
  .err { background: #fed7d7; color: #822727; }
</style>
</head>
<body>
  <h1>App 1: Form Pengirim (PHP-FPM + nginx)</h1>
  <p class="subtitle">Satu file ini merangkap form (GET) dan endpoint relay ke App 2 (POST)</p>

  <div class="diagram">
    <div class="box">Browser</div>
    <span>&rarr;</span>
    <div class="box">nginx:8001</div>
    <span>&rarr;</span>
    <div class="box">app1-php (index.php)</div>
    <span>&rarr;</span>
    <div class="box">nginx:8002</div>
    <span>&rarr;</span>
    <div class="box">app2-php (index.php)</div>
  </div>

  <form id="dataForm">
    <label>Nama
      <input type="text" name="nama" required placeholder="Masukkan nama">
    </label>
    <label>Pesan
      <input type="text" name="pesan" required placeholder="Masukkan pesan">
    </label>
    <button type="submit" id="btn">Kirim ke App 2</button>
  </form>

  <div id="status"></div>
  <pre id="result" style="display:none;"></pre>

  <script>
    document.getElementById('dataForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = document.getElementById('btn');
      const status = document.getElementById('status');
      const result = document.getElementById('result');

      const data = { nama: this.nama.value, pesan: this.pesan.value };

      btn.disabled = true;
      result.style.display = 'none';
      const t0 = performance.now();
      status.innerHTML = 'Mengirim ke App 1, lalu diteruskan ke App 2... <span class="badge wait">menunggu</span>';

      try {
        // POST ke file ini sendiri (index.php) -- dibedakan dari GET lewat
        // method request, ditangani di bagian PHP paling atas file ini.
        const res = await fetch('/index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data),
        });
        const json = await res.json();
        const elapsed = ((performance.now() - t0) / 1000).toFixed(2);

        if (!res.ok) {
          status.innerHTML = `Gagal <span class="badge err">error</span> (${elapsed} detik)`;
        } else {
          status.innerHTML = `Berhasil diteruskan ke App 2 <span class="badge ok">selesai</span> (${elapsed} detik)`;
          this.reset();
        }
        result.textContent = JSON.stringify(json, null, 2);
        result.style.display = 'block';
      } catch (err) {
        status.innerHTML = 'Gagal menghubungi App 1 <span class="badge err">error</span>';
      } finally {
        btn.disabled = false;
      }
    });
  </script>
</body>
</html>
