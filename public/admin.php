<?php
declare(strict_types=1);
/* admin.php — empresas y usuarios (solo ADMIN) */
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';
$user = require_login_page(['ADMIN']);
page_head('Administración');
?>
<body>
<?php page_nav($user, 'admin.php'); ?>
<div class="container py-3">
  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="cardish">
        <div class="section-title">Empresas</div>
        <form id="fCo" class="row g-2 mb-3">
          <input type="hidden" id="co_id">
          <div class="col-8"><input id="co_nombre" class="form-control form-control-sm" placeholder="Razón social" required></div>
          <div class="col-4"><select id="co_tipo" class="form-select form-select-sm">
            <option>TITULAR</option><option selected>CONTRATISTA</option><option>SUBCONTRATISTA</option><option>PROVEEDOR</option><option>OTRO</option></select></div>
          <div class="col-4"><input id="co_cuit" class="form-control form-control-sm" placeholder="CUIT"></div>
          <div class="col-5"><input id="co_art" class="form-control form-control-sm" placeholder="ART"></div>
          <div class="col-3 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" id="co_activo" checked><label class="form-check-label small" for="co_activo">Activa</label></div></div>
          <div class="col-12 d-flex gap-2"><button class="btn btn-primary btn-sm">Guardar</button><button type="button" class="btn btn-outline-secondary btn-sm" onclick="fCo.reset();co_id.value=''">Nueva</button></div>
        </form>
        <table class="table table-sm table-hover mb-0"><thead><tr><th>Empresa</th><th>Tipo</th><th>CUIT</th><th>ART</th><th></th></tr></thead><tbody id="tCo"></tbody></table>
      </div>
    </div>
    <div class="col-12 col-lg-6">
      <div class="cardish">
        <div class="section-title">Usuarios</div>
        <form id="fU" class="row g-2 mb-3">
          <input type="hidden" id="u_id">
          <div class="col-6"><input id="u_name" class="form-control form-control-sm" placeholder="Nombre" required></div>
          <div class="col-6"><input id="u_email" type="email" class="form-control form-control-sm" placeholder="Email" required></div>
          <div class="col-5"><select id="u_role" class="form-select form-select-sm"><option value="SUPERVISOR">Supervisor (carga)</option><option value="LECTOR">Lector</option><option value="ADMIN">Administrador</option></select></div>
          <div class="col-5"><input id="u_pass" type="password" class="form-control form-control-sm" placeholder="Contraseña (mín. 8)" autocomplete="new-password"></div>
          <div class="col-2 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" id="u_active" checked><label class="form-check-label small" for="u_active">Activo</label></div></div>
          <div class="col-12 d-flex gap-2"><button class="btn btn-primary btn-sm">Guardar</button><button type="button" class="btn btn-outline-secondary btn-sm" onclick="fU.reset();u_id.value=''">Nuevo</button></div>
        </form>
        <table class="table table-sm table-hover mb-0"><thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th></th></tr></thead><tbody id="tU"></tbody></table>
        <div class="small-muted mt-2">Al editar, dejá la contraseña vacía para no cambiarla.</div>
      </div>
    </div>
  </div>
</div>
<?php page_scripts(); ?>
<script>
const $ = id => document.getElementById(id);
let CO = [], US = [];
async function loadCo() {
  CO = (await api('companies.php?all=1')).data;
  $('tCo').innerHTML = CO.map((c, i) => `<tr class="${c.activo ? '' : 'text-muted'}"><td>${esc(c.nombre)}</td><td class="small">${esc(c.tipo)}</td><td class="small">${esc(c.cuit || '')}</td><td class="small">${esc(c.art || '')}</td>
    <td><button class="btn btn-sm btn-link p-0" onclick="editCo(${i})">editar</button></td></tr>`).join('');
}
function editCo(i) { const c = CO[i]; co_id.value = c.id; co_nombre.value = c.nombre; co_tipo.value = c.tipo; co_cuit.value = c.cuit || ''; co_art.value = c.art || ''; co_activo.checked = c.activo; }
$('fCo').onsubmit = async e => {
  e.preventDefault();
  try { await api('companies.php', { json: { id: co_id.value || null, nombre: co_nombre.value, tipo: co_tipo.value, cuit: co_cuit.value, art: co_art.value, activo: co_activo.checked } });
    toast('Empresa guardada'); fCo.reset(); co_id.value = ''; loadCo(); } catch (err) { toast(err.message, 'danger'); }
};
async function loadU() {
  US = (await api('users.php')).data;
  $('tU').innerHTML = US.map((u, i) => `<tr class="${u.active ? '' : 'text-muted'}"><td>${esc(u.full_name)}</td><td class="small">${esc(u.email)}</td><td class="small">${esc(u.role)}</td>
    <td><button class="btn btn-sm btn-link p-0" onclick="editU(${i})">editar</button></td></tr>`).join('');
}
function editU(i) { const u = US[i]; u_id.value = u.id; u_name.value = u.full_name; u_email.value = u.email; u_role.value = u.role; u_active.checked = u.active; u_pass.value = ''; }
$('fU').onsubmit = async e => {
  e.preventDefault();
  try { await api('users.php', { json: { id: u_id.value || null, full_name: u_name.value, email: u_email.value, role: u_role.value, password: u_pass.value, active: u_active.checked } });
    toast('Usuario guardado'); fU.reset(); u_id.value = ''; loadU(); } catch (err) { toast(err.message, 'danger'); }
};
loadCo().catch(e => toast(e.message, 'danger')); loadU().catch(e => toast(e.message, 'danger'));
</script>
</body></html>
