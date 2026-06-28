async function importChessCom(){
  const msg=document.getElementById('ccMsg'); msg.textContent='Importando desde Chess.com...';
  const username=document.getElementById('ccUser').value.trim(); const limit=parseInt(document.getElementById('ccLimit').value||'20',10);
  const r=await fetch('api/chesscom.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username,limit})});
  const j=await r.json();
  msg.innerHTML=j.ok?`Encontradas: ${j.found}. Nuevas: ${j.added}. Duplicadas: ${j.skipped}. <a href="analysis-pending.php">Ver cola</a>`:j.error;
}
