window.addEventListener("DOMContentLoaded", function() {
  console.log("✅ FanCtrl Plus Dashboard script loaded.");

  const tileHtml = `
    <tbody title="FanCtrl Plus" id="fanctrlplus-tile">
      <tr><td>
        <i class="fa fa-spinner fa-spin"></i>
        <div class="section">FanCtrl Plus<br>
          <span id="fanctrlplus-dashboard-status">Loading...</span>
        </div>
      </td></tr>
    </tbody>
  `;

  let container = document.getElementById("db_box2") || document.getElementById("db_box1");
  if (container && !document.getElementById("fanctrlplus-tile")) {
    container.insertAdjacentHTML("beforeend", tileHtml);
    console.log("✅ FanCtrl Plus tile inserted.");
  } else {
    console.warn("❌ FanCtrl Plus tile insert failed: container not found.");
  }

  function updateFanDashboard() {
    fetch('/plugins/fanctrlplus/include/FanctrlLogic.php?op=status')
      .then(res => res.json())
      .then(data => {
        const status = (data.status === 'running') ? '🟢 Running' : '🔴 Stopped';
        const el = document.getElementById("fanctrlplus-dashboard-status");
        if (el) el.innerHTML = status;
      });
  }

  updateFanDashboard();
  setInterval(updateFanDashboard, 30000);
});
