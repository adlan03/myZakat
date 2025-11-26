// keluarga.js — versi stabil (tambah/hapus, reindex, check-all sinkron, infaq OK)
const hargaTetap  = Number(SETTING?.harga  ?? 0);
const berasTetap  = Number(SETTING?.beras  ?? 0);
const jagungTetap = Number(SETTING?.jagung ?? 0);
const infaqValue  = Number(SETTING?.infaqValue ?? 15000);

const $  = s => document.querySelector(s);
const $$ = s => document.querySelectorAll(s);
const on = (el, ev, fn) => { if (el) el.addEventListener(ev, fn); };

function formatRp(n){ return Number(n||0).toLocaleString('id-ID'); }

/** Hitung total & tampilkan */
function hitungTotal() {
  const jUang   = $$(".uang:checked").length;
  const jBeras  = $$(".beras:checked").length;
  const jJagung = $$(".jagung:checked").length;

  const infaqChecked = $("#infaq")?.checked;
  const infaq = infaqChecked ? infaqValue : 0;

  const totalUang   = (jUang * hargaTetap) + infaq;
  const totalBeras  = (jBeras * berasTetap);
  const totalJagung = (jJagung * jagungTetap);

  $("#totalUang")   && ($("#totalUang").innerText   = `Total Uang: Rp ${formatRp(totalUang)}`);
  $("#totalBeras")  && ($("#totalBeras").innerText  = `Total Beras: ${totalBeras} kg`);
  $("#totalJagung") && ($("#totalJagung").innerText = `Total Jagung: ${totalJagung} kg`);
  $("#totalInfaq")  && ($("#totalInfaq").innerText  = `Total Infaq: Rp ${formatRp(infaq)}`);

  const uangMasuk = parseFloat($("#uangDiterima")?.value || 0);
  const kembali = uangMasuk - totalUang;
  $("#kembalian") && ($("#kembalian").value = (!isNaN(kembali) && kembali >= 0) ? `Rp ${formatRp(kembali)}` : "Belum cukup");
}

/** Check-all: set semua dan enforce eksklusif per baris */
function toggleSemua(selector, aktif) {
  $$(selector).forEach(cb => {
    cb.checked = aktif;
    enforceRowExclusive(cb); // jaga eksklusif per baris
  });
  updateCheckAllStates();
  hitungTotal();
}

/** Sinkronkan status check-all (checked/indeterminate) */
function updateCheckAllStates(){
  [
    {all:"#checkAllUang",  item:".uang"},
    {all:"#checkAllBeras", item:".beras"},
    {all:"#checkAllJagung",item:".jagung"}
  ].forEach(g => {
    const all = $(g.all);
    const items = $$(g.item);
    if (!all || items.length === 0) return;
    const every = [...items].every(i => i.checked);
    const some  = [...items].some(i => i.checked);
    all.checked = every;
    all.indeterminate = !every && some;
  });
}

/** Re-index name agar berurutan setelah tambah/hapus */
function reindexRows(){
  const rows = $$("#tabelKeluarga tbody tr");
  rows.forEach((tr, i) => {
    // radio JK
    tr.querySelectorAll('input[type="radio"][name^="jk["]').forEach(r => { r.name = `jk[${i}]`; });
    // checkbox
    tr.querySelector('.uang')   ?.setAttribute('name', `uang[${i}]`);
    tr.querySelector('.beras')  ?.setAttribute('name', `beras[${i}]`);
    tr.querySelector('.jagung') ?.setAttribute('name', `jagung[${i}]`);
    // tombol hapus (min 1 baris)
    const btn = tr.querySelector('.btn-del');
    if (btn) btn.disabled = (rows.length === 1);
  });
  updateCheckAllStates();
}

/** Pastikan per baris hanya salah satu dari uang/beras/jagung */
function enforceRowExclusive(target){
  const tr = target.closest('tr');
  if (!tr) return;
  if (target.classList.contains('uang')) {
    const b = tr.querySelector('.beras');  if (b) b.checked = false;
    const j = tr.querySelector('.jagung'); if (j) j.checked = false;
  } else if (target.classList.contains('beras')) {
    const u = tr.querySelector('.uang');   if (u) u.checked = false;
    const j = tr.querySelector('.jagung'); if (j) j.checked = false;
  } else if (target.classList.contains('jagung')) {
    const u = tr.querySelector('.uang');   if (u) u.checked = false;
    const b = tr.querySelector('.beras');  if (b) b.checked = false;
  }
}

/** Buat <tr> baru */
function createRow(idx){
  const tr = document.createElement("tr");
  tr.innerHTML = `
    <td><input type="text" name="nama[]" value=""></td>
    <td>
      <label><input type="radio" name="jk[${idx}]" value="L">L</label>
      <label><input type="radio" name="jk[${idx}]" value="P">P</label>
    </td>
    <td><input type="checkbox" class="uang"   name="uang[${idx}]"></td>
    <td><input type="checkbox" class="beras"  name="beras[${idx}]"></td>
    <td><input type="checkbox" class="jagung" name="jagung[${idx}]"></td>
    <td><button type="button" class="btn-del">Hapus</button></td>
  `;
  return tr;
}

document.addEventListener("DOMContentLoaded", () => {
  // Check-all (null-safe)
  on($("#checkAllUang"),  "change", e => toggleSemua(".uang",  e.target.checked));
  on($("#checkAllBeras"), "change", e => toggleSemua(".beras", e.target.checked));
  on($("#checkAllJagung"),"change", e => toggleSemua(".jagung",e.target.checked));

  // Delegasi perubahan di dalam tabel
  on($("#tabelKeluarga"), "change", e => {
    if (e.target.matches(".uang, .beras, .jagung")) {
      enforceRowExclusive(e.target);
      updateCheckAllStates();
      hitungTotal();
    }
  });

  // Infaq di luar tabel → pasang listener tersendiri
  on($("#infaq"), "change", hitungTotal);

  // Input uang diterima
  on($("#uangDiterima"), "input", hitungTotal);

  // Hapus baris (delegation)
  on($("#tabelKeluarga"), "click", e => {
    if (e.target.classList.contains("btn-del")) {
      const tbody = $("#tabelKeluarga tbody");
      if (!tbody) return;
      const rows = tbody.querySelectorAll("tr");
      if (rows.length <= 1) return; // minimal 1 baris
      e.target.closest("tr").remove();
      reindexRows();
      hitungTotal();
    }
  });

  // Tambah baris
  on($("#tambah"), "click", () => {
    const tbody = $("#tabelKeluarga tbody");
    if (!tbody) return;
    const idx = tbody.children.length;
    tbody.appendChild(createRow(idx));
    reindexRows();
  });

  // Init
  reindexRows();
  hitungTotal();
});
