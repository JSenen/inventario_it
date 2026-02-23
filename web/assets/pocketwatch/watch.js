(function () {
  const hourEl = document.getElementById("pw-hour");
  const minuteEl = document.getElementById("pw-minute");
  const secondEl = document.getElementById("pw-second");

  if (!hourEl || !minuteEl || !secondEl) return;

  function setRot(el, deg) {
    el.style.transform = `translate(-50%, -100%) rotate(${deg}deg)`;
  }

  function tick() {
    const now = new Date();

    const ms = now.getMilliseconds();
    const s = now.getSeconds() + ms / 1000;
    const m = now.getMinutes() + s / 60;
    const h = (now.getHours() % 12) + m / 60;

    setRot(secondEl, s * 6);
    setRot(minuteEl, m * 6);
    setRot(hourEl, h * 30);
  }

  function loop() {
    tick();
    requestAnimationFrame(loop);
  }
  loop();
})();
