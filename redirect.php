<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>广告跳转中 - 杨爽短链接</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
       background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
       min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.card {
  background: white; border-radius: 16px; padding: 40px;
  max-width: 680px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.ad-title { font-size: 14px; color: #999; margin-bottom: 16px; text-align: center; }
.ad-label { display: inline-block; background:#f0f0f0; color:#888; font-size:11px;
            padding:2px 8px; border-radius:4px; margin-bottom:12px; }
.ad-content { min-height: 120px; border: 1px solid #eee; border-radius: 8px;
              padding: 16px; margin-bottom: 24px; overflow: hidden; }
.ad-content img { max-width: 100%; border-radius: 6px; }
.target-info { background: #f8f9fa; border-radius: 8px; padding: 12px 16px;
               margin-bottom: 20px; font-size: 13px; color: #666; word-break: break-all; }
.target-info span { color: #333; font-weight: 500; }
.skip-btn {
  display: block; width: 100%; padding: 14px;
  background: linear-gradient(135deg, #667eea, #764ba2);
  color: white; border: none; border-radius: 10px;
  font-size: 16px; cursor: pointer; text-align: center;
  transition: opacity 0.2s;
}
.skip-btn:hover { opacity: 0.9; }
.skip-btn:disabled { background: #ccc; cursor: not-allowed; }
.progress-bar { height: 4px; background: #eee; border-radius: 2px; margin-bottom: 16px; overflow: hidden; }
.progress-fill { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2);
                 transition: width 1s linear; }
.footer { text-align: center; font-size: 12px; color: #ccc; margin-top: 20px; }
</style>
</head>
<body>
<div class="card">
  <div class="ad-title">🔗 正在跳转，请稍候</div>
  <span class="ad-label">广告</span>
  <div class="ad-content"><?= $adContent ?></div>
  <div class="target-info">目标链接：<span><?= $targetUrl ?></span></div>

  <?php if ($countdown > 0): ?>
  <div class="progress-bar">
    <div class="progress-fill" id="progressFill" style="width:100%"></div>
  </div>
  <?php endif; ?>

  <?php if ($skipMode === 'manual'): ?>
    <button class="skip-btn" id="skipBtn" <?= $countdown > 0 ? 'disabled' : '' ?>>
      <?= $countdown > 0 ? str_replace('{countdown}', $countdown, htmlspecialchars($btnText)) : '立即跳转' ?>
    </button>
  <?php else: ?>
    <button class="skip-btn" id="skipBtn">
      <?= $countdown > 0 ? str_replace('{countdown}', $countdown, htmlspecialchars($btnText)) : '立即跳转' ?>
    </button>
  <?php endif; ?>

  <div class="footer">广告由杨爽短链接系统提供</div>
</div>

<script>
const targetUrl  = "<?= $targetUrl ?>";
const countdown  = <?= $countdown ?>;
const skipMode   = "<?= $skipMode ?>";
const btn        = document.getElementById('skipBtn');
const fillEl     = document.getElementById('progressFill');

btn.addEventListener('click', () => { window.location.href = targetUrl; });

if (countdown > 0) {
  let remaining = countdown;
  const btnOrigText = "<?= addslashes(str_replace('{countdown}', ''+$countdown, $btnText)) ?>";
  const interval = setInterval(() => {
    remaining--;
    const pct = (remaining / countdown) * 100;
    if (fillEl) fillEl.style.width = pct + '%';
    const txt = btnOrigText.replace(/\d+秒/, remaining + '秒').replace(/\{countdown\}/, remaining);
    btn.textContent = txt + '（' + remaining + '秒）';
    if (remaining <= 0) {
      clearInterval(interval);
      if (skipMode === 'auto') {
        window.location.href = targetUrl;
      } else {
        btn.disabled = false;
        btn.textContent = '立即跳转';
      }
    }
  }, 1000);

  if (skipMode === 'auto') {
    setTimeout(() => { window.location.href = targetUrl; }, countdown * 1000 + 200);
  }
}
</script>
</body>
</html>
