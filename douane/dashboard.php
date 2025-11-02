<?php
require_once '../includes/auth_check.php';
checkRole(['douanier']);
require_once '../config/database.php';
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Douane - Frais de transit</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body class="bg-gray-100">
  <div id="toast" class="hidden fixed right-6 top-4 z-50 px-4 py-3 rounded-md bg-emerald-600 text-white shadow-lg pointer-events-none transition transform duration-200">
    <span id="toastText">Enregistré</span>
  </div>
  <div class="fixed inset-y-0 left-0 w-64 bg-emerald-900 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out" id="sidebar">
    <div class="flex items-center justify-center h-16 bg-emerald-800">
      <i class="fas fa-warehouse text-2xl mr-2"></i>
      <span class="text-xl font-semibold">Douane</span>
    </div>
    <nav class="mt-8">
      <a href="dashboard.php" class="flex items-center px-4 py-3 text-white bg-emerald-800"><i class="fas fa-coins mr-3"></i>Frais de transit</a>
    </nav>
  </div>

  <div class="ml-0 lg:ml-64">
    <header class="bg-white shadow-sm border-b border-gray-200">
      <div class="flex items-center justify-between px-6 py-4">
        <button class="lg:hidden text-gray-600 hover:text-gray-900" onclick="document.getElementById('sidebar').classList.toggle('-translate-x-full')"><i class="fas fa-bars text-xl"></i></button>
        <div class="flex items-center gap-3">
          <div class="text-right">
            <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></p>
            <p class="text-sm text-gray-500">Agent douanier</p>
          </div>
          <a href="../auth/logout.php" class="inline-flex items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded"><i class="fas fa-sign-out-alt mr-2"></i>Déconnexion</a>
        </div>
      </div>
    </header>

    <main class="p-6">
      <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-900 mb-4">Frais de transit</h1>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
          <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div class="flex items-end gap-3 flex-wrap">
              <div>
                <label class="block text-sm text-gray-600 mb-1">Période</label>
                <select id="scope" class="border rounded px-3 py-2">
                  <option value="month">Mois courant</option>
                  <option value="year">Année courante</option>
                  <option value="custom">Personnalisé</option>
                </select>
              </div>
              <div id="rangeFields" class="hidden items-end gap-3">
                <div>
                  <label class="block text-sm text-gray-600 mb-1">Début</label>
                  <input id="start" type="date" class="border rounded px-3 py-2" />
                </div>
                <div>
                  <label class="block text-sm text-gray-600 mb-1">Fin</label>
                  <input id="end" type="date" class="border rounded px-3 py-2" />
                </div>
                <button id="btnFilter" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded">Appliquer</button>
              </div>
              <button id="btnReload" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded">Actualiser</button>
            </div>
            <div class="flex items-center gap-3">
              <a target="_blank" href="../autorite/api/export_rapport.php?report=frais_transit&scope=month&format=pdf" class="inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded"><i class="fas fa-file-pdf mr-2"></i>Rapport Frais PDF</a>
              <a target="_blank" href="../autorite/api/export_rapport.php?report=frais_transit&scope=month&format=xlsx" class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded"><i class="fas fa-file-excel mr-2"></i>Rapport Frais Excel</a>
            </div>
          </div>
          <div class="mt-4 overflow-auto">
            <table class="min-w-full border">
              <thead>
                <tr class="bg-gray-50 text-left text-sm">
                  <th class="px-3 py-2 border whitespace-nowrap">Voie</th>
                  <th class="px-3 py-2 border whitespace-nowrap">Immatriculation</th>
                  <th class="px-3 py-2 border whitespace-nowrap">Chauffeur/Capitaine</th>
                  <th class="px-3 py-2 border whitespace-nowrap">Date entrée</th>
                  <th class="px-3 py-2 border whitespace-nowrap">Marchandises</th>
                  <th class="px-3 py-2 border whitespace-nowrap">Frais</th>
                  <th class="px-3 py-2 border whitespace-nowrap">Total frais</th>
                  <th class="px-3 py-2 border whitespace-nowrap">Action</th>  
                </tr>
              </thead>
              <tbody id="entriesBody" class="text-sm"></tbody>
            </table>
            <p id="entriesEmpty" class="text-sm text-gray-500 mt-3 hidden">Aucune entrée trouvée pour la période.</p>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-3">Enregistrer les frais</h2>
          <p id="selectedInfo" class="text-sm text-gray-600 mb-4"></p>
          <form id="feesForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm text-gray-600 mb-1">Type</label>
                <select name="type" id="type" class="w-full border rounded px-3 py-2">
                  <option value="camion">Camion</option>
                  <option value="bateau">Bateau</option>
                </select>
              </div>
              <div>
                <label class="block text-sm text-gray-600 mb-1">Immatriculation</label>
                <div class="flex gap-2">
                  <input id="ident" type="text" class="w-full border rounded px-3 py-2" placeholder="Ex: KB1234A" />
                </div>
                <p id="refInfo" class="text-xs text-gray-500 mt-1"></p>
              </div>
            </div>

            <input type="hidden" id="ref_id" />

            <div>
              <label class="block text-sm text-gray-600 mb-1">THC (Terminal)</label>
              <input name="thc" id="thc" type="number" min="0" step="0.01" class="w-full border rounded px-3 py-2" />
            </div>
            <div>
              <label class="block text-sm text-gray-600 mb-1">Magasinage</label>
              <input name="magasinage" id="magasinage" type="number" min="0" step="0.01" class="w-full border rounded px-3 py-2" />
            </div>
            <div>
              <label class="block text-sm text-gray-600 mb-1">Droits de douane</label>
              <input name="droits_douane" id="droits_douane" type="number" min="0" step="0.01" class="w-full border rounded px-3 py-2" />
            </div>
            <div>
              <label class="block text-sm text-gray-600 mb-1">Surestaries</label>
              <input name="surestaries" id="surestaries" type="number" min="0" step="0.01" class="w-full border rounded px-3 py-2" />
            </div>

            <div class="md:col-span-2 flex items-center gap-3">
              <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded inline-flex items-center"><i class="fas fa-save mr-2"></i>Enregistrer</button>
              <button id="btnLoad" type="button" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded">Modifier</button>
              <button id="btnCancel" type="button" class="px-4 py-2 bg-white border hover:bg-gray-50 text-gray-700 rounded">Annuler</button>
              <span id="status" class="text-sm text-gray-600"></span>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>

  <script>
    const scopeEl = document.getElementById('scope');
    const rangeFields = document.getElementById('rangeFields');
    const entriesBody = document.getElementById('entriesBody');
    const entriesEmpty = document.getElementById('entriesEmpty');
    const selectedInfo = document.getElementById('selectedInfo');
    // Reset only fees fields
    function resetFees() {
      document.getElementById('thc').value = '';
      document.getElementById('magasinage').value = '';
      document.getElementById('droits_douane').value = '';
      document.getElementById('surestaries').value = '';
      document.getElementById('status').textContent = '';
    }
    // Full reset: also reset type and immatriculation
    function resetAll() {
      document.getElementById('type').value = 'camion';
      document.getElementById('ref_id').value = '';
      document.getElementById('ident').value = '';
      document.getElementById('refInfo').textContent = '';
      document.getElementById('selectedInfo').textContent = '';
      resetFees();
      document.getElementById('ident').focus();
    }

    scopeEl.addEventListener('change', () => {
      if (scopeEl.value === 'custom') { rangeFields.classList.remove('hidden'); }
      else { rangeFields.classList.add('hidden'); fetchEntries(); }
    });

    document.getElementById('btnFilter').addEventListener('click', (e) => {
      e.preventDefault();
      fetchEntries();
    });
    document.getElementById('btnReload').addEventListener('click', (e) => {
      e.preventDefault();
      fetchEntries();
    });

    function renderFees(fe) {
      const toNum = (v) => (v === null || v === undefined || v === '') ? 0 : Number(v);
      const thc = toNum(fe?.thc).toFixed(2);
      const mag = toNum(fe?.magasinage).toFixed(2);
      const drt = toNum(fe?.droits_douane).toFixed(2);
      const sur = toNum(fe?.surestaries).toFixed(2);
      return `THC ${thc} • Mag ${mag} • Droits ${drt} • Sur ${sur}`;
    }

    function renderTotalFees(fe) {
      if (!fe) return '0.00';
      const toNum = (v) => (v === null || v === undefined || v === '') ? 0 : Number(v);
      const total = toNum(fe.thc) + toNum(fe.magasinage) + toNum(fe.droits_douane) + toNum(fe.surestaries);
      return total.toFixed(2);
    }

    function renderMarchandises(list) {
      if (!list || !list.length) return '-';
      return list.map(m => `${m.type} (${m.quantite} x ${m.poids ?? 0} kg)`).join(', ');
    }

    function selectEntry(item) {
      document.getElementById('type').value = item.type;
      document.getElementById('ref_id').value = item.ref_id;
      document.getElementById('ident').value = item.ident;
      document.getElementById('refInfo').textContent = `${item.type === 'camion' ? 'Camion' : 'Bateau'} ${item.ident}`;
      selectedInfo.textContent = `Sélection: ${item.type === 'camion' ? 'Camion' : 'Bateau'} ${item.ident} — ${item.partie || ''} — ${item.date_entree}`;
      // preload fees into form
      document.getElementById('thc').value = item.fees?.thc ?? '';
      document.getElementById('magasinage').value = item.fees?.magasinage ?? '';
      document.getElementById('droits_douane').value = item.fees?.droits_douane ?? '';
      document.getElementById('surestaries').value = item.fees?.surestaries ?? '';
    }

    async function fetchEntries() {
      let url = 'api/list_entrees.php?scope=' + encodeURIComponent(scopeEl.value);
      if (scopeEl.value === 'custom') {
        const s = document.getElementById('start').value;
        const e = document.getElementById('end').value;
        if (!s || !e) { alert('Veuillez indiquer la période.'); return; }
        url += '&start=' + encodeURIComponent(s) + '&end=' + encodeURIComponent(e);
      }
      const resp = await fetch(url);
      const data = await resp.json();
      if (!data || !data.success) { entriesBody.innerHTML = ''; entriesEmpty.classList.remove('hidden'); return; }
      const items = data.items || [];
      entriesBody.innerHTML = '';
      if (items.length === 0) { entriesEmpty.classList.remove('hidden'); return; }
      entriesEmpty.classList.add('hidden');
      for (const it of items) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="px-3 py-2 border">${it.type === 'camion' ? 'Camion' : 'Bateau'}</td>
          <td class="px-3 py-2 border font-medium">${it.ident}</td>
          <td class="px-3 py-2 border">${it.partie ?? ''}</td>
          <td class="px-3 py-2 border">${it.date_entree ?? ''}</td>
          <td class="px-3 py-2 border">${renderMarchandises(it.marchandises)}</td>
          <td class="px-3 py-2 border">${renderFees(it.fees)}</td>
          <td class="px-3 py-2 border text-right">${renderTotalFees(it.fees)}</td>
          <td class="px-3 py-2 border text-right"><button class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded select-btn">Sélectionner</button></td>
        `;
        tr.querySelector('.select-btn').addEventListener('click', (e) => { e.preventDefault(); selectEntry(it); window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); });
        entriesBody.appendChild(tr);
      }
    }

    async function findRef() {
      const type = document.getElementById('type').value;
      const identEl = document.getElementById('ident');
      const ident = identEl.value.trim();
      if (!ident) { alert('Veuillez entrer une immatriculation'); return; }
      const url = `api/find_ref.php?type=${encodeURIComponent(type)}&ident=${encodeURIComponent(ident)}&mouvement=entree`;
      const resp = await fetch(url);
      const data = await resp.json();
      const info = document.getElementById('refInfo');
      if (data && data.success) {
        document.getElementById('ref_id').value = data.ref_id;
        info.textContent = data.label || `${type === 'camion' ? 'Camion' : 'Bateau'} ${ident}`;
        return true;
      } else {
        info.textContent = data.message || 'Référence introuvable';
        return false;
      }
    }

    async function loadFees() {
      const type = document.getElementById('type').value;
      const refId = document.getElementById('ref_id').value;
      if (!refId) { alert('Veuillez d\'abord chercher et sélectionner une référence.'); return; }
      const resp = await fetch('api/get_frais.php?type=' + encodeURIComponent(type) + '&ref_id=' + encodeURIComponent(refId) + '&mouvement=entree');
      const data = await resp.json();
      if (data && data.success && data.item) {
        const it = data.item;
        document.getElementById('thc').value = it.thc ?? '';
        document.getElementById('magasinage').value = it.magasinage ?? '';
        document.getElementById('droits_douane').value = it.droits_douane ?? '';
        document.getElementById('surestaries').value = it.surestaries ?? '';
      } else {
        document.getElementById('thc').value = '';
        document.getElementById('magasinage').value = '';
        document.getElementById('droits_douane').value = '';
        document.getElementById('surestaries').value = '';
      }
    }

    async function performSave(successToastText) {
      const type = document.getElementById('type').value;
      const refId = document.getElementById('ref_id').value;
      const payload = {
        type,
        ref_id: parseInt(refId, 10),
        mouvement: 'entree',
        thc: document.getElementById('thc').value ? parseFloat(document.getElementById('thc').value) : null,
        magasinage: document.getElementById('magasinage').value ? parseFloat(document.getElementById('magasinage').value) : null,
        droits_douane: document.getElementById('droits_douane').value ? parseFloat(document.getElementById('droits_douane').value) : null,
        surestaries: document.getElementById('surestaries').value ? parseFloat(document.getElementById('surestaries').value) : null
      };
      if (!payload.ref_id) { alert('Veuillez sélectionner une référence valide.'); return; }
      const status = document.getElementById('status');
      try {
        status.textContent = 'Enregistrement en cours...';
        const resp = await fetch('api/save_frais.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const data = await resp.json();
        if (data && data.success) {
          status.textContent = 'Enregistré.';
          // Toast success
          const toast = document.getElementById('toast');
          const toastText = document.getElementById('toastText');
          toastText.textContent = successToastText || 'Frais enregistrés avec succès';
          toast.classList.remove('hidden');
          toast.style.opacity = '1';
          setTimeout(() => toast.classList.add('hidden'), 2500);

          // Refresh entries table immediately
          fetchEntries();

          // Reset form fields
          document.getElementById('ref_id').value = '';
          document.getElementById('ident').value = '';
          document.getElementById('refInfo').textContent = '';
          document.getElementById('selectedInfo').textContent = '';
          document.getElementById('thc').value = '';
          document.getElementById('magasinage').value = '';
          document.getElementById('droits_douane').value = '';
          document.getElementById('surestaries').value = '';
          document.getElementById('type').value = 'camion';
          // Focus back to ident for quick next input
          document.getElementById('ident').focus();
        } else {
          status.textContent = data.message || 'Erreur lors de l\'enregistrement';
        }
      } catch (err) {
        status.textContent = 'Erreur réseau';
      } finally {
        setTimeout(() => status.textContent = '', 2500);
      }
    }

    async function saveFees(e) {
      e.preventDefault();
      await performSave('Frais enregistrés avec succès');
    }

    async function handleModifyClick(e) {
      e.preventDefault();
      const refId = document.getElementById('ref_id').value;
      const ident = document.getElementById('ident').value.trim();
      if (!refId) {
        if (!ident) { alert("Veuillez saisir l'immatriculation, puis réessayer."); return; }
        const ok = await findRef();
        if (!ok) { return; }
        // Référence résolue: procéder directement à la modification
        await performSave('Frais modifiés avec succès');
        return;
      }
      // Si une référence est déjà sélectionnée, on enregistre directement comme modification
      await performSave('Frais modifiés avec succès');
    }
    document.getElementById('btnLoad').addEventListener('click', handleModifyClick);
    document.getElementById('btnCancel').addEventListener('click', (e) => { e.preventDefault(); resetAll(); });
    document.getElementById('feesForm').addEventListener('submit', saveFees);
    // Initial load
    fetchEntries();
  </script>
</body>
</html>
