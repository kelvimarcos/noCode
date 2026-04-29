const { useEffect, useMemo, useState } = React;

function App() {
  const [tab, setTab] = useState('roleta');
  const [data, setData] = useState({ config: { premios: [] }, metrics: {} });
  const [message, setMessage] = useState('');

  const load = async () => {
    const res = await fetch('/api/config');
    const json = await res.json();
    setData(json);
  };

  useEffect(() => { load(); }, []);

  const conversao = useMemo(() => {
    const { giros = 0, ganhos = 0 } = data.metrics || {};
    return giros ? `${Math.round((ganhos / giros) * 100)}%` : '0%';
  }, [data.metrics]);

  const salvar = async () => {
    await fetch('/api/config', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data.config) });
    setMessage('Configuração salva com sucesso.');
  };

  const girar = async () => {
    const res = await fetch('/api/spin', { method: 'POST' });
    const json = await res.json();
    if (!res.ok) return setMessage(json.message);
    setMessage(`Parabéns! Você ganhou: ${json.premio.texto}`);
    setData((prev) => ({ ...prev, metrics: json.metrics }));
  };

  return <div className="container">
    <header className="header"><strong>🎯 Spin & Win Admin</strong><button className="danger">Sair</button></header>
    <div className="tabs">
      {['roleta','premios','aparencia','metricas'].map((t) => <button key={t} className={`tab ${tab===t ? 'active' : ''}`} onClick={() => setTab(t)}>{t.toUpperCase()}</button>)}
    </div>

    {tab === 'roleta' && <div className="grid-2">
      <section className="card"><h3>Preview</h3><div className="wheel" /><button className="primary" onClick={girar}>Girar</button></section>
      <section className="card">
        <h3>Controle de acesso</h3>
        <label>Limite de giros por usuário</label>
        <input type="number" value={data.config.limitePorUsuario || 1} onChange={(e)=>setData({...data, config: {...data.config, limitePorUsuario: Number(e.target.value)}})} />
        <label style={{display:'flex', gap:8, marginTop: 12}}><input type="checkbox" checked={!!data.config.bloqueada} onChange={(e)=>setData({...data, config: {...data.config, bloqueada: e.target.checked}})} />Roleta bloqueada</label>
        <div style={{marginTop:12}}><button className="primary" onClick={salvar}>Salvar</button></div>
      </section>
    </div>}

    {tab === 'premios' && <section className="card"><h3>Prêmios</h3>{data.config.premios?.map((p) => <div key={p.id} className="premio-item"><div className="row"><input value={p.tipo} readOnly /><input value={p.texto} readOnly /><input value={p.valor} readOnly /><input value={p.prob} readOnly /><input value={p.cor} readOnly /></div></div>)}</section>}

    {tab === 'aparencia' && <section className="card"><h3>Aparência</h3><p style={{color:'#9f9ac6'}}>Tema escuro, cards com bordas suaves e componentes reutilizáveis.</p></section>}

    {tab === 'metricas' && <section className="card"><h3>Métricas</h3><div className="stat-grid"><div className="stat">Acessos: {data.metrics.acessos || 0}</div><div className="stat">Giros: {data.metrics.giros || 0}</div><div className="stat">Ganhos: {data.metrics.ganhos || 0}</div><div className="stat">Conversão: {conversao}</div></div></section>}

    {!!message && <section className="card modal"><p>{message}</p></section>}
  </div>
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
