document.addEventListener('DOMContentLoaded', () => {
  /* News scroller */
  const newsScroll = document.getElementById('newsScroll');
  const prevNews = document.getElementById('prevNews');
  const nextNews = document.getElementById('nextNews');
  if (newsScroll && prevNews && nextNews) {
    prevNews.addEventListener('click', () => newsScroll.scrollBy({ left: -320, behavior: 'smooth' }));
    nextNews.addEventListener('click', () => newsScroll.scrollBy({ left: 320, behavior: 'smooth' }));
  }

  /* Reveal on scroll */
  const io = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('show'); io.unobserve(e.target); } });
  }, { threshold: .14 });
  document.querySelectorAll('.reveal').forEach(el => io.observe(el));

  /* Count-up for stat numbers */
  function countUp(node, duration = 800) {
    const target = parseInt((node.textContent || '0').replace(/[^0-9]/g, ''), 10) || 0;
    let start = null;
    function step(ts) {
      if (!start) start = ts;
      const p = Math.min((ts - start) / duration, 1);
      node.textContent = Math.floor(target * (0.1 + 0.9 * Math.pow(p, 0.6))).toLocaleString();
      if (p < 1) requestAnimationFrame(step);
    }
    node.textContent = '0';
    requestAnimationFrame(step);
  }
  document.querySelectorAll('.h4.mb-0').forEach(h => countUp(h));

  /* Ripple on buttons */
  document.addEventListener('click', e => {
    const btn = e.target.closest('.btn'); if (!btn) return;
    const r = document.createElement('span');
    const rect = btn.getBoundingClientRect();
    r.style.position = 'absolute';
    r.style.inset = '0';
    r.style.borderRadius = 'inherit';
    r.style.pointerEvents = 'none';
    r.style.background = `radial-gradient(circle at ${e.clientX - rect.left}px ${e.clientY - rect.top}px, rgba(13,110,253,.25), transparent 45%)`;
    r.style.opacity = '0';
    r.style.transition = 'opacity .6s ease';
    btn.style.position = 'relative';
    btn.appendChild(r);
    requestAnimationFrame(() => {
      r.style.opacity = '1';
      setTimeout(() => { r.style.opacity = '0'; setTimeout(() => r.remove(), 300); }, 180);
    });
  });

  /* Weather: exact Bugo coords + shimmer + float icon */
/* Weather: exact Bugo coords + shimmer + float icon (tolerant) */
const WX = {
  elTemp: document.getElementById('wxTemp'),
  elDesc: document.getElementById('wxDesc'),
  elIcon: document.getElementById('wxIcon'),
  elMeta: document.getElementById('wxMeta'),     // optional
  elWind: document.getElementById('wxWind'),     // optional
  elUpdated: document.getElementById('wxUpdated')// optional
};

if (WX.elTemp && WX.elDesc) {
  WX.elTemp.classList.add('shimmer');
  WX.elDesc.classList.add('shimmer');

  const map = (code)=>{
    const m={0:['Clear','☀️'],1:['Mainly clear','🌤️'],2:['Partly cloudy','⛅'],3:['Overcast','☁️'],
      45:['Fog','🌫️'],48:['Rime fog','🌫️'],51:['Drizzle','🌦️'],53:['Drizzle','🌦️'],55:['Drizzle','🌦️'],
      61:['Rain','🌧️'],63:['Rain','🌧️'],65:['Heavy rain','🌧️'],71:['Snow','🌨️'],73:['Snow','🌨️'],75:['Snow','🌨️'],95:['Thunderstorm','⛈️']};
    return m[code] || ['Weather','🌡️'];
  };

  (async ()=>{
    try{
      const lat=8.5281, lon=124.7556;
      const url=`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,weather_code,wind_speed_10m&timezone=Asia%2FManila`;
      const r=await fetch(url);
      if(!r.ok) throw new Error('wx http');
      const j=await r.json();
      const cur=j.current||{};
      const [desc,icon]=map(cur.weather_code ?? 0);

      [WX.elTemp, WX.elDesc].forEach(n=>{ n.classList.remove('shimmer'); n.style.transition='opacity .35s ease'; n.style.opacity='0'; });
      setTimeout(()=>{
        WX.elTemp.textContent = Math.round(cur.temperature_2m ?? 0)+"°C";
        WX.elDesc.textContent = desc;
        if (WX.elIcon) { WX.elIcon.textContent = icon; WX.elIcon.classList.add('float'); }

        const windTxt = `Wind ${Math.round(cur.wind_speed_10m||0)} km/h`;
        const updatedTxt = `Updated ${new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}`;

        if (WX.elMeta) WX.elMeta.textContent = `${windTxt} · ${updatedTxt}`;
        if (WX.elWind) WX.elWind.textContent = windTxt;
        if (WX.elUpdated) WX.elUpdated.textContent = updatedTxt;

        [WX.elTemp, WX.elDesc].forEach(n=> n.style.opacity='1');
      },140);
    }catch(e){
      [WX.elTemp, WX.elDesc].forEach(n=>n.classList.remove('shimmer'));
      WX.elDesc.textContent='Unable to load weather.';
      if (WX.elMeta) WX.elMeta.textContent='';
    }
  })();
}

});
