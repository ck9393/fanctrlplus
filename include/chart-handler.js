// chart-handler.js - Show temp→PWM chart for a fan block

window.showFanChart = function (btn) {
  const block = btn.closest('.fan-block');
  if (!block) return;

  const getNum = (selector) => {
    const el = block.querySelector(selector);
    if (!el) return null;
    const val = el.value.replace(/[^\d.]/g, '');
    return val ? parseFloat(val) : null;
  };

  const getSelectVal = (selector) => {
    const el = block.querySelector(selector);
    return el ? el.value : '';
  };

  const name = getSelectVal('[name^="custom["]') || '(Unnamed)';
  const pwmMin = getNum('[name^="pwm_percent["]');
  const pwmMax = getNum('[name^="max_percent["]');
  const diskSelected = block.querySelector('[name^="disks["]')?.selectedOptions?.length > 0;
  const tempLow = getNum('[name^="low["]');
  const tempHigh = getNum('[name^="high["]');
  const cpuEnabled = getSelectVal('[name^="cpu_enable["]') === '1';
  const cpuLow = getNum('[name^="cpu_min_temp["]');
  const cpuHigh = getNum('[name^="cpu_max_temp["]');

  if ([pwmMin, pwmMax, tempLow, tempHigh].some(v => v === null)) {
    Swal.fire('⚠️ Missing input', 'Please fill in all Disk Temp and PWM values.', 'warning');
    return;
  }

  // 插值生成曲线数据点
  const makeLinePoints = (x1, y1, x2, y2, segments = x2 - x1) => {
  const data = [];
  for (let i = 0; i <= segments; i++) {
      const ratio = i / segments;
      const x = x1 + (x2 - x1) * ratio;
      const y = y1 + (y2 - y1) * ratio;
      data.push({ x, y });
  }
  return data;
  };

  const makePointRadiusArray = (length) => {
    return Array.from({ length }, (_, i) => (i === 0 || i === length - 1) ? 4 : 0);
  };

  const datasets = [];

  if (diskSelected && tempLow !== null && tempHigh !== null) {
    const diskPoints = makeLinePoints(tempLow, pwmMin, tempHigh, pwmMax);
    const diskRadius = makePointRadiusArray(diskPoints.length);

    datasets.push({
    label: 'Disk Temp → PWM (%)',
    data: diskPoints,
    borderColor: '#4285f4',
    backgroundColor: 'rgba(66,133,244,0.1)',
    borderWidth: 2,
    pointRadius: diskRadius,
    pointHoverRadius: 6,
    fill: false,
    tension: 0.4
    });
  }

  if (cpuEnabled && cpuLow !== null && cpuHigh !== null) {
    const cpuPoints = makeLinePoints(cpuLow, pwmMin, cpuHigh, pwmMax);
    const cpuRadius = makePointRadiusArray(cpuPoints.length);

    datasets.push({
    label: 'CPU Temp → PWM (%)',
    data: cpuPoints,
    borderColor: '#db4437',
    backgroundColor: 'rgba(219,68,55,0.1)',
    borderWidth: 2,
    pointRadius: cpuRadius,
    pointHoverRadius: 6,
    fill: false,
    tension: 0.4
    });
  }

  // 控制权注解说明文字
  let footerNote = '';

  if (!cpuEnabled && !diskSelected) {
    footerNote = '⚠️ No rules defined — fan will not be controlled';
    } else if (cpuEnabled && !diskSelected) {
    footerNote = '💡 No disk selected — only CPU rule applies';
    } else if (!cpuEnabled && diskSelected) {
    footerNote = '💡 CPU control is disabled — only Disk rule applies';
    } else {
    footerNote = '💡 CPU and Disk rules are active — Fan PWM = max(Disk, CPU)';
    }
    
  Swal.fire({
    title: `📈 ${name}`,
    html: `
      <div id="fan-chart-wrapper" style="padding:0 10px;">
        <canvas id="fan-chart" style="width: 100%; height: auto;"></canvas>
        <div style="margin-top: 8px; font-size: 13px; color: #666; text-align: center;">${footerNote}</div>
      </div>`,

    customClass: 'chart-swal',
    didOpen: () => {
      setTimeout(() => {
        const canvas = document.getElementById('fan-chart');
        const wrapper = document.getElementById('fan-chart-wrapper');
        if (!canvas || !wrapper) return;

        // 设置实际像素宽高，避免模糊
        const width = wrapper.offsetWidth;
        canvas.width = width;
        canvas.height = 400;

        const ctx = canvas.getContext('2d');

        // 假设所有 dataset 都共用相同的横轴数据
        // 从所有 datasets 中提取所有 x 值（温度）
        let allTemps = [];
        datasets.forEach(ds => {
          if (ds.data) {
            ds.data.forEach(point => {
              if (typeof point.x === 'number') allTemps.push(point.x);
            });
          }
        });

        const minTemp = Math.min(...allTemps);
        const maxTemp = Math.max(...allTemps);
        const tempRange = maxTemp - minTemp;

        // 自动判断步进：小范围细一点，大范围粗一点
        let stepSize = 5;
        if (tempRange <= 10) {
          stepSize = 1;
        } else if (tempRange <= 20) {
          stepSize = 2;
        }

        // 初始化图表
        new Chart(ctx, {
          type: 'line',
          data: { datasets },
          options: {
            responsive: false,
            scales: {
              x: {
                type: 'linear',
                title: { display: true, text: 'Temperature (°C)' },
                min: minTemp - 1,
                max: maxTemp + 1,
                ticks: {
                  stepSize: stepSize,
                  autoSkip: false
                }
              },
              y: {
                min: 0,
                max: 100,
                title: { display: true, text: 'Fan Speed (%)' },
                ticks: { stepSize: 10 }
              }
            },
            plugins: {
              legend: {
                position: 'bottom',
                labels: {
                  usePointStyle: false,
                  pointStyle: 'line',
                  boxWidth: 30,
                  boxHeight: 0
                }
              },
              
              interaction: {
                mode: 'nearest',
                intersect: false,
                axis: 'x'
              },

              tooltip: {
              usePointStyle: false,
              pointStyle: 'line',
              boxWidth: 10,
              boxHeight: 0,
              mode: 'nearest',
              intersect: false,
              callbacks: {
                title: function (tooltipItems) {
                  const x = tooltipItems[0].parsed.x;
                  return `${x}°C`;
                },
                label: function (context) {
                const label = context.dataset.label.includes('Disk') ? 'Disk Temp' : 'CPU Temp';
                const percent = context.parsed.y;
                const pwm = Math.round(percent * 2.55);
                return `${label} → Fan Speed = ${percent.toFixed(0)}% (PWM ${pwm})`;
               }
              }
             }
            }
          }
        });
      }, 10);
    }
  });
};