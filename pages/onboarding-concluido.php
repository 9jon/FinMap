<?php
require_once __DIR__ . '/../includes/autenticacao.php';
exigirUsuarioAutenticado();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#f8faf8">
  <title>FinMap</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { font-family: Inter, sans-serif; color: #18231b; background: #f8faf8; }
    * { box-sizing: border-box; }
    body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; }
    main { width: min(100%, 390px); text-align: center; }
    .brand-logo { display: block; width: 68px; height: 68px; object-fit: contain; margin: 0 auto 42px; }
    .route { display: block; width: 100%; height: 105px; overflow: visible; margin: 0 auto 28px; }
    .route-base, .route-draw { fill: none; stroke-linecap: round; stroke-linejoin: round; }
    .route-base { stroke: #dce8df; stroke-width: 2; }
    .route-draw { stroke: #20aa4b; stroke-width: 2; stroke-dasharray: 360; stroke-dashoffset: 360; animation: draw-route 850ms cubic-bezier(.48,0,.32,1) forwards; }
    .start-dot { fill: #a7b4aa; }
    .end-dot { fill: #20aa4b; transform-box: fill-box; transform-origin: center; animation: arrive 300ms 780ms ease-out both; }
    .moving-dot { fill: #20aa4b; filter: drop-shadow(0 1px 3px #20aa4b55); }
    h1 { margin: 0 0 8px; font-size: clamp(1.55rem, 6vw, 1.85rem); font-weight: 650; letter-spacing: -.055em; }
    p { margin: 0; color: #718075; font-size: .96rem; line-height: 1.65; }
    .open-link { display: inline-block; margin-top: 25px; padding: 9px 2px; color: #16813a; font-size: .9rem; font-weight: 600; text-decoration: none; }
    .open-link:hover { color: #11672e; }
    .open-link:focus-visible { outline: 2px solid #16813a; outline-offset: 4px; border-radius: 4px; }
    body.leaving main { animation: leave 280ms ease-in forwards; }
    @keyframes draw-route { to { stroke-dashoffset: 0; } }
    @keyframes arrive { from { opacity: .3; transform: scale(.65); } to { opacity: 1; transform: scale(1); } }
    @keyframes leave { to { opacity: 0; transform: translateY(-5px); } }
    @media (prefers-reduced-motion: reduce) { .route-draw { stroke-dashoffset: 0; animation: none; } .end-dot { animation: none; } body.leaving main { animation: none; } }
  </style>
  <link rel="stylesheet" href="../assets/css/page-entrance.css?v=2">
</head>
<body>
  <main aria-labelledby="transition-title">
    <img class="brand-logo" src="../assets/img/finmap-logo-cadastro.png" alt="FinMap">
    <svg class="route" viewBox="0 0 360 105" role="img" aria-label="Trajeto do cadastro até o seu painel">
      <path class="route-base" d="M34 76 C92 76 93 30 151 30 S218 78 270 78 S313 49 326 34"/>
      <path id="routePath" class="route-draw" d="M34 76 C92 76 93 30 151 30 S218 78 270 78 S313 49 326 34"/>
      <circle class="start-dot" cx="34" cy="76" r="4"/>
      <circle class="end-dot" cx="326" cy="34" r="6"/>
      <circle class="moving-dot" r="4">
        <animateMotion dur="1.05s" fill="freeze" path="M34 76 C92 76 93 30 151 30 S218 78 270 78 S313 49 326 34"/>
      </circle>
    </svg>
    <h1 id="transition-title">Seu painel está pronto.</h1>
    <p>Indo para o seu mapa financeiro.</p>
    <a class="open-link" href="dashboard.php">Abrir painel agora</a>
  </main>
  <script>
    const goToDashboard = () => {
      document.body.classList.add('leaving');
      window.setTimeout(() => window.location.replace('dashboard.php'), 280);
    };
    const delay = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 500 : 1350;
    window.setTimeout(goToDashboard, delay);
  </script>
  <script src="../assets/js/page-entrance.js?v=3" defer></script>
</body>
</html>