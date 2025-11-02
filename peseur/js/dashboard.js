// Fonctions utilitaires
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('-translate-x-full');
    }
}

function closePesageModal() {
    const modal = document.getElementById('pesageModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function calculateTotalPoids() {
    let total = 0;
    document.querySelectorAll('input[name$="[poids]"]').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    return total;
}

function updatePoidsTotal() {
    const total = calculateTotalPoids();
    const totalElement = document.getElementById('totalPoids');
    if (totalElement) {
        totalElement.textContent = total.toFixed(2);
    }
    return total;
}

// Fonction pour afficher les détails d'un camion
function voirDetails(camionId) {
    const modal = document.getElementById('pesageModal');
    const content = document.getElementById('pesageContent');
    
    if (!modal || !content) {
        console.error('Éléments du modal introuvables');
        return;
    }
    
    content.innerHTML = '<div class="flex justify-center items-center p-8"><div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div></div>';
    modal.classList.remove('hidden');
    
    fetch(`api/camion-details.php?id=${camionId}&mode=sortie`)
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Erreur réseau');
                }).catch(() => {
                    throw new Error('Erreur lors de la communication avec le serveur');
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Données reçues:', data); // Log pour le débogage
            
            if (!data.success) {
                throw new Error(data.message || 'Erreur lors du chargement des données');
            }
            
            const camion = data.camion || {};
            const pesage = data.pesage || {};
            const hasSortie = Array.isArray(data.marchandises_sortie) && data.marchandises_sortie.length > 0;
            const marchandises = hasSortie ? (data.marchandises_sortie || []) : (data.marchandises || []);
            const hasPesage = data.hasPesage || false;
            
            // Vérifier si les données essentielles sont présentes
            if (!camion || Object.keys(camion).length === 0) {
                throw new Error('Aucune donnée de camion disponible');
            }
            
            // Construction du contenu du modal
            const estSurcharge = hasPesage && camion.est_surcharge == 1;
            const surchargeClass = estSurcharge ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800';
            const surchargeText = estSurcharge ? `SURCHARGÉ (${Math.abs(camion.depassement || 0).toFixed(2)} kg)` : 'Conforme';
            
            let detailsContent = `
                <div class="space-y-6">
                    <!-- En-tête avec statut de surcharge -->
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-4 py-3 ${estSurcharge ? 'bg-red-50' : 'bg-green-50'} border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg leading-6 font-medium ${estSurcharge ? 'text-red-800' : 'text-green-800'}">
                                    ${estSurcharge ? '⚠️ Camion en surcharge' : '✅ Poids conforme'}
                                </h3>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium ${surchargeClass}">
                                    ${surchargeText}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Détails du camion -->
                        <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
                            <dl class="sm:divide-y sm:divide-gray-200">
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-gray-500">
                                        Numéro d'immatriculation
                                    </dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                        ${camion.immatriculation || 'Non renseigné'}
                                    </dd>
                                </div>
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-gray-500">
                                        Chauffeur
                                    </dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                        ${camion.chauffeur || 'Non renseigné'}
                                    </dd>
                                </div>
                                
                                <!-- Section Poids -->
                                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 bg-gray-50">
                                    <dt class="text-sm font-medium text-gray-900">
                                        Poids (kg)
                                    </dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                        <div class="grid grid-cols-1 gap-4">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">PTAC (Poids Total Autorisé en Charge):</span>
                                                <span class="font-medium">${camion.ptac ? camion.ptac.toLocaleString('fr-FR') : 'N/A'}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">PTAV (Poids à Vide):</span>
                                                <span class="font-medium">${camion.ptav ? camion.ptav.toLocaleString('fr-FR') : 'N/A'}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Charge maximale autorisée:</span>
                                                <span class="font-medium">${camion.charge_autorisee ? camion.charge_autorisee.toLocaleString('fr-FR') : 'N/A'}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Poids total pesé:</span>
                                                <span class="font-medium ${estSurcharge ? 'text-red-600' : 'text-green-600'}">
                                                    ${camion.poids_total_pese ? camion.poids_total_pese.toLocaleString('fr-FR') : 'N/A'}
                                                    ${estSurcharge ? `(+${Math.abs(camion.depassement).toLocaleString('fr-FR')} kg)` : ''}
                                                </span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">PTRA (Poids Total Roulant Autorisé):</span>
                                                <span class="font-medium">${camion.ptra ? camion.ptra.toLocaleString('fr-FR') : 'N/A'}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Charge à l'essieu:</span>
                                                <span class="font-medium">${camion.charge_essieu ? camion.charge_essieu.toLocaleString('fr-FR') + ' kg/essieu' : 'N/A'}</span>
                                            </div>
                                        </div>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    
                    ${marchandises.length > 0 ? `
                        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                            <div class="px-4 py-5 sm:px-6 bg-gray-50">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    Marchandises
                                </h3>
                            </div>
                            <div class="border-t border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poids (kg)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        ${marchandises.map(marchandise => `
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    ${marchandise.type_marchandise}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ${marchandise.quantite || '0'}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    ${marchandise.poids ? parseFloat(marchandise.poids).toFixed(2) : '0.00'}
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="flex justify-end">
                        <button type="button" onclick="closePesageModal()" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Fermer
                        </button>
                        
                    </div>
                </div>
            `;
            
            content.innerHTML = detailsContent;
        })
        .catch(error => {
            console.error('Erreur:', error);
            let errorMessage = 'Une erreur est survenue lors du chargement des détails du camion.';
            
            // Messages d'erreur plus détaillés
            if (error.message.includes('Camion non trouvé')) {
                errorMessage = 'Le camion demandé est introuvable.';
            } else if (error.message.includes('ID du camion requis')) {
                errorMessage = 'Aucun identifiant de camion fourni.';
            } else if (error.message) {
                errorMessage += `\n${error.message}`;
            }
            
            content.innerHTML = `
                <div class="bg-red-50 border-l-4 border-red-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-red-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700 whitespace-pre-line">
                                ${errorMessage}
                                ${error.message ? `<span class="text-xs opacity-75 block mt-1">${error.message}</span>` : ''}
                            </p>
                            <div class="mt-4">
                                <button onclick="this.closest('div[role=dialog]').classList.add('hidden')" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Fermer
                                </button>
                                <button onclick="voirDetails(${camionId})" class="ml-3 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-sync-alt mr-2"></i>Réessayer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
}

// Ouvrir la modale et afficher le formulaire de pesage d'un camion
function peserCamion(camionId) {
    const modal = document.getElementById('pesageModal');
    const content = document.getElementById('pesageContent');
    if (!modal || !content) {
        console.error('Éléments du modal introuvables');
        return;
    }
    // Chargement
    content.innerHTML = '<div class="flex justify-center items-center p-8"><div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500"></div></div>';
    modal.classList.remove('hidden');

    fetch(`api/camion-details.php?id=${camionId}&mode=sortie`)
        .then(r => r.json())
        .then(data => {
            if (!data || !data.success) {
                throw new Error(data && data.message ? data.message : 'Erreur lors du chargement des données');
            }
            const camion = data.camion || {};
            const hasSortie = Array.isArray(data.marchandises_sortie) && data.marchandises_sortie.length > 0;
            const marchandises = hasSortie
                ? data.marchandises_sortie
                : (Array.isArray(data.marchandises) ? data.marchandises : []);

            const lignesMarchandises = marchandises.map((m, idx) => `
                <tr class="${idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                    <td class="px-4 py-2 text-sm text-gray-900">${m.type_marchandise || 'Non spécifié'}</td>
                    <td class="px-4 py-2">
                        <input type="number" step="1" min="0" name="marchandises[${m.id}][quantite]" value="${(m.quantite !== undefined && m.quantite !== null) ? m.quantite : ''}"
                               class="w-40 rounded-lg border-gray-300 shadow focus:border-blue-500 focus:ring-blue-500 text-base py-2 px-3" />
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" step="0.01" min="0" name="marchandises[${m.id}][poids]" value="${(m.poids !== undefined && m.poids !== null) ? m.poids : ''}"
                               oninput="updatePoidsTotal()"
                               class="w-48 rounded-lg border-gray-300 shadow focus:border-blue-500 focus:ring-blue-500 text-base py-2 px-3" />
                    </td>
                </tr>
            `).join('');

            content.innerHTML = `
                <form id=\"pesageForm\" method=\"POST\" action=\"dashboard.php\" class=\"space-y-6\">
                    <div id=\"pesageError\" class=\"hidden bg-red-50 border-l-4 border-red-400 text-red-700 px-4 py-3 rounded\"></div>
                    <input type=\"hidden\" name=\"action\" value=\"peser_camion\" />
                    <input type=\"hidden\" name=\"camion_id\" value=\"${camionId}\" />

                    <div class=\"grid grid-cols-1 md:grid-cols-2 gap-4\">
                        <div>
                            <label class=\"block text-sm font-medium text-gray-700\">PTAV (kg)</label>
                            <input type=\"number\" step=\"0.01\" min=\"0\" name=\"ptav\" value=\"${(camion.ptav || 0)}\"
                                   class=\"mt-1 block w-full rounded-lg border-gray-300 shadow focus:border-blue-500 focus:ring-blue-500 text-base py-2.5 px-3\" required />
                        </div>
                        <div>
                            <label class=\"block text-sm font-medium text-gray-700\">PTAC (kg)</label>
                            <input type=\"number\" step=\"0.01\" min=\"0\" name=\"ptac\" value=\"${(camion.ptac || 0)}\"
                                   class=\"mt-1 block w-full rounded-lg border-gray-300 shadow focus:border-blue-500 focus:ring-blue-500 text-base py-2.5 px-3\" required />
                        </div>
                        <div>
                            <label class=\"block text-sm font-medium text-gray-700\">PTRA (kg)</label>
                            <input type=\"number\" step=\"0.01\" min=\"0\" name=\"ptra\" value=\"${(camion.ptra || 0)}\"
                                   class=\"mt-1 block w-full rounded-lg border-gray-300 shadow focus:border-blue-500 focus:ring-blue-500 text-base py-2.5 px-3\" required />
                        </div>
                        <div>
                            <label class=\"block text-sm font-medium text-gray-700\">Charge à l'essieu (kg)</label>
                            <input type=\"number\" step=\"0.01\" min=\"0\" name=\"charge_essieu\" value=\"${(camion.charge_essieu || 0)}\"
                                   class=\"mt-1 block w-full rounded-lg border-gray-300 shadow focus:border-blue-500 focus:ring-blue-500 text-base py-2.5 px-3\" />
                        </div>
                    </div>

                    <div class=\"bg-white border rounded-md overflow-hidden\">
                        <div class=\"px-4 py-3 bg-gray-50 flex items-center justify-between\">
                            <h4 class=\"text-sm font-medium text-gray-900\">${hasSortie ? 'Marchandises (sortie)' : 'Marchandises'}</h4>
                            <div class=\"text-sm text-gray-600\">Total poids saisi: <span id=\"totalPoids\">0.00</span> kg</div>
                        </div>
                        <div class=\"overflow-x-auto\">
                            <table class=\"min-w-full divide-y divide-gray-200\">
                                <thead class=\"bg-gray-50\">
                                    <tr>
                                        <th class=\"px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase\">Type</th>
                                        <th class=\"px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase\">Quantité</th>
                                        <th class=\"px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase\">Poids (kg)</th>
                                    </tr>
                                </thead>
                                <tbody class=\"divide-y divide-gray-200\">${lignesMarchandises || '<tr><td colspan=\"3\" class=\"px-4 py-3 text-sm text-gray-500\">Aucune marchandise</td></tr>'}</tbody>
                            </table>
                        </div>
                    </div>

                    <div class=\"px-4 py-3 bg-gray-50 flex justify-end space-x-3 rounded-b-md\">
                        <button type=\"button\" onclick=\"document.getElementById('pesageModal').classList.add('hidden')\" class=\"px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500\">
                            Annuler
                        </button>
                        <button type=\"submit\" class=\"px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500\">
                            Enregistrer le pesage
                        </button>
                    </div>
                </form>
            `;

            // Initialiser le total poids
            updatePoidsTotal();

            // Gestion soumission AJAX pour rester sur la modal et afficher les erreurs
            const form = document.getElementById('pesageForm');
            if (form) {
                form.addEventListener('submit', async (ev) => {
                    ev.preventDefault();
                    const errorBox = document.getElementById('pesageError');
                    if (errorBox) {
                        errorBox.classList.add('hidden');
                        errorBox.textContent = '';
                    }
                    const fd = new FormData(form);
                    fd.set('ajax', '1');
                    try {
                        const res = await fetch('dashboard.php', {
                            method: 'POST',
                            body: fd,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        let data = null;
                        try { data = await res.json(); } catch (_) {}
                        if (!res.ok || !data || data.success === false) {
                            const msg = (data && data.message) ? data.message : 'Erreur lors de l\'enregistrement du pesage';
                            if (errorBox) {
                                errorBox.textContent = msg;
                                errorBox.classList.remove('hidden');
                            } else {
                                alert(msg);
                            }
                            return;
                        }
                        const modal = document.getElementById('pesageModal');
                        if (modal) modal.classList.add('hidden');
                        location.reload();
                    } catch (e) {
                        if (errorBox) {
                            errorBox.textContent = 'Erreur réseau. Veuillez réessayer.';
                            errorBox.classList.remove('hidden');
                        } else {
                            alert('Erreur réseau. Veuillez réessayer.');
                        }
                    }
                });
            }
        })
        .catch(err => {
            console.error('Erreur:', err);
            content.innerHTML = `<div class=\"bg-red-50 border-l-4 border-red-400 p-4\"><p class=\"text-sm text-red-700\">${err.message || 'Erreur lors du chargement'}</p></div>`;
        });
}

// Exposer la fonction pour les onclick inline
if (typeof window !== 'undefined') {
    window.peserCamion = peserCamion;
}

// Fonction pour mettre à jour les options des champs de sélection de marchandise
function updateMarchandiseSelects() {
    const selects = document.querySelectorAll('.marchandise-select');
    const selectedValues = Array.from(selects)
        .map(select => select.value)
        .filter(value => value !== '');
    
    selects.forEach(select => {
        const currentValue = select.value;
        Array.from(select.options).forEach(option => {
            if (option.value === '' || option.value === currentValue) return;
            option.hidden = selectedValues.includes(option.value) && option.value !== currentValue;
        });
    });
}

// Fonction pour ajouter un champ de marchandise
function ajouterChampMarchandise(container, marchandises) {
    if (!container) {
        console.error('Erreur: conteneur non trouvé');
        return;
    }
    
    // Vérifier que marchandises est bien un tableau
    if (!Array.isArray(marchandises) || marchandises.length === 0) {
        console.error('Erreur: Aucune marchandise disponible', marchandises);
        return;
    }
    
    const index = document.querySelectorAll('.marchandise-item').length;
    const div = document.createElement('div');
    div.className = 'marchandise-item grid grid-cols-4 gap-4';
    
    // Créer les options de la liste déroulante des marchandises
    const options = marchandises.length > 0 
        ? marchandises.map(m => 
            `<option value="${m.id}">${m.nom}${m.unite_mesure ? ' (' + m.unite_mesure + ')' : ''}</option>`
          ).join('')
        : '<option value="">Aucune marchandise disponible</option>';
    
    div.innerHTML = `
        <div>
            <label class="block text-sm font-medium text-gray-700">Marchandise</label>
            <select name="marchandises[${index}][type_marchandise_id]" required
                   class="marchandise-select mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                   onchange="updateMarchandiseSelects()">
                <option value="">Sélectionnez une marchandise</option>
                ${options}
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Quantité</label>
            <input type="number" name="marchandises[${index}][quantite]" step="0.01" min="0.01" required
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Poids total (kg)</label>
            <input type="number" name="marchandises[${index}][poids]" step="0.01" min="0.01" required
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
        </div>
        <div class="flex items-end">
            <button type="button" onclick="this.closest('.marchandise-item').remove(); updateMarchandiseSelects();" 
                    class="text-red-600 hover:text-red-800">
                <i class="fas fa-times"></i> Supprimer
            </button>
        </div>
    `;
    container.appendChild(div);
    updateMarchandiseSelects();
}

// Fonction pour autoriser la sortie d'un camion
async function autoriserSortie(camionId) {
    try {
        // Charger les données nécessaires avant d'afficher le formulaire
        await loadInitialData();
        
        // Vérifier que les données sont disponibles
        if (!marchandisesDisponibles || marchandisesDisponibles.length === 0) {
            throw new Error('Impossible de charger les données des marchandises');
        }
        
        // Créer le contenu HTML du formulaire
        const formHTML = `
        <form id="sortieForm" class="text-left">
            <input type="hidden" name="action" value="autoriser_sortie">
            <input type="hidden" name="camion_id" value="${camionId}">
            
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            Veuillez vérifier les informations avant d'autoriser la sortie du camion.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" id="retourVide" name="retour_vide" 
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <span class="ml-2 text-sm text-gray-700">Retour à vide</span>
                </label>
            </div>

            <div id="marchandisesContainer" class="space-y-4 mb-4">
                <div class="flex justify-between items-center">
                    <h4 class="text-sm font-medium text-gray-700">Marchandises à exporter</h4>
                    <button type="button" id="addMarchandise"
                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-plus mr-1"></i> Ajouter une marchandise
                    </button>
                </div>
                <!-- Les champs de marchandises seront ajoutés ici dynamiquement -->
            </div>
            
            <div class="mb-4">
                <label for="port_destination" class="block text-sm font-medium text-gray-700">Port de destination</label>
                <select id="port_destination" name="port_destination" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="">Sélectionnez un port</option>
                </select>
            </div>
            
            <div class="mb-4">
                <label for="observations" class="block text-sm font-medium text-gray-700">Observations</label>
                <textarea id="observations" name="observations" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                          placeholder="Informations complémentaires (optionnel)"></textarea>
            </div>
        </form>`;

        // Afficher le formulaire avec SweetAlert2
        const swalInstance = Swal.fire({
        title: 'Autorisation de sortie',
        html: formHTML,
        showCancelButton: true,
        showConfirmButton: true,
        confirmButtonText: 'Valider la sortie',
        cancelButtonText: 'Annuler',
        showCloseButton: true,
        width: '600px',
        focusConfirm: false,
        didOpen: () => {
            // Cibler les éléments dans le popup SweetAlert
            const popup = Swal.getPopup();
            const container = popup ? popup.querySelector('#marchandisesContainer') : document.getElementById('marchandisesContainer');
            const addButton = popup ? popup.querySelector('#addMarchandise') : document.getElementById('addMarchandise');
            const retourVideCheckbox = popup ? popup.querySelector('#retourVide') : document.getElementById('retourVide');
            const portSelect = popup ? popup.querySelector('#port_destination') : document.getElementById('port_destination');

            // Charger les ports depuis l'API (URL absolue)
            fetch('/gestionPortBujumbura/peseur/api/get-ports.php')
                .then(response => response.json())
                .then(ports => {
                    console.debug('Ports (modal didOpen) reçus:', ports);
                    // Mettre à jour la liste déroulante des ports
                    if (portSelect) {
                        if (!Array.isArray(ports) || ports.length === 0) {
                            portSelect.innerHTML = '<option value="">Aucun port disponible</option>';
                        } else {
                            portSelect.innerHTML = '<option value="">Sélectionnez un port</option>' + 
                                ports.map(port => 
                                    `<option value="${port.id}">${port.nom} (${port.pays})</option>`
                                ).join('');
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des ports:', error);
                    if (portSelect) {
                        portSelect.innerHTML = '<option value="">Erreur de chargement des ports</option>';
                    }
                });

            // Charger les données nécessaires et (ré)attacher les handlers marchandises
            loadInitialData()
                .then(() => {
                    try { refreshPortsList(portSelect); } catch (e) {}
                    try { refreshPortsList(portSelect); } catch (e) {}
                    // Attacher/forcer le gestionnaire du bouton Ajouter Marchandise
                    if (container && addButton) {
                        addButton.onclick = function() {
                            if (Array.isArray(marchandisesDisponibles) && marchandisesDisponibles.length > 0) {
                                ajouterChampMarchandise(container, marchandisesDisponibles);
                            } else {
                                console.error('Aucune marchandise disponible');
                            }
                        };
                    } else if (popup) {
                        // Secours: délégation d'événement dans le popup
                        popup.addEventListener('click', (ev) => {
                            const btn = ev.target.closest('#addMarchandise');
                            if (btn && container) {
                                if (Array.isArray(marchandisesDisponibles) && marchandisesDisponibles.length > 0) {
                                    ajouterChampMarchandise(container, marchandisesDisponibles);
                                } else {
                                    console.error('Aucune marchandise disponible');
                                }
                            }
                        });
                    }
                    // Ajouter un premier champ si nécessaire
                    if (container.querySelectorAll('.marchandise-item').length === 0) {
                        if (Array.isArray(marchandisesDisponibles) && marchandisesDisponibles.length > 0) {
                            ajouterChampMarchandise(container, marchandisesDisponibles);
                        }
                    }
                })
                .catch(err => console.error('Erreur lors du chargement des données (didOpen):', err));
            // Fonction pour ajouter un champ de marchandise
            const ajouterChamp = () => {
                if (marchandisesDisponibles && marchandisesDisponibles.length > 0) {
                    ajouterChampMarchandise(container, marchandisesDisponibles);
                } else {
                    console.error('Aucune marchandise disponible');
                    swalInstance.update({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Aucune marchandise disponible. Veuillez réessayer plus tard.',
                        confirmButtonText: 'OK'
                    });
                }
            };

            // Ajouter un écouteur d'événement pour le bouton d'ajout
            if (addButton) {
                addButton.onclick = ajouterChamp;
            }

            // Gérer le changement de l'état du checkbox retour à vide
            if (retourVideCheckbox) {
                const updateMarchandisesVisibility = (isChecked) => {
                    const marchandisesSection = container.closest('.marchandises-section');
                    if (marchandisesSection) {
                        marchandisesSection.style.display = isChecked ? 'none' : 'block';
                    }
                    
                    if (isChecked) {
                        // Vider les champs de marchandise existants
                        container.querySelectorAll('.marchandise-item').forEach(item => item.remove());
                    } else if (container.querySelectorAll('.marchandise-item').length === 0) {
                        // Ajouter un champ de marchandise si aucun n'existe
                        ajouterChamp();
                    }
                };

                // Initialiser l'état
                updateMarchandisesVisibility(retourVideCheckbox.checked);
                
                // Ajouter l'écouteur d'événement
                retourVideCheckbox.onchange = (e) => {
                    updateMarchandisesVisibility(e.target.checked);
                };
            }

            // Ajouter un premier champ de marchandise si nécessaire et si pas en mode retour à vide
            if (container && container.querySelectorAll('.marchandise-item').length === 0 && 
                (!retourVideCheckbox || !retourVideCheckbox.checked)) {
                ajouterChamp();
            }

            // Gérer le changement de l'état du checkbox retour à vide
            if (retourVideCheckbox) {
                retourVideCheckbox.addEventListener('change', function() {
                    if (!container) return;
                    
                    if (this.checked) {
                        // Vider le conteneur et ajouter un message
                        container.innerHTML = `
                            <div class="flex justify-between items-center">
                                <h4 class="text-sm font-medium text-gray-700">Marchandises à exporter</h4>
                                <button type="button" id="addMarchandise"
                                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-plus mr-1"></i> Ajouter une marchandise
                                </button>
                            </div>
                            <p class="text-sm text-gray-500">Aucune marchandise (retour à vide)</p>`;
                        
                        // Réattacher le gestionnaire d'événements au nouveau bouton
                        const newAddButton = document.getElementById('addMarchandise');
                        if (newAddButton) {
                            newAddButton.addEventListener('click', ajouterChamp);
                        }
                    } else {
                        // Réinitialiser le conteneur
                        container.innerHTML = `
                            <div class="flex justify-between items-center">
                                <h4 class="text-sm font-medium text-gray-700">Marchandises à exporter</h4>
                                <button type="button" id="addMarchandise"
                                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-plus mr-1"></i> Ajouter une marchandise
                                </button>
                            </div>`;
                        
                        // Réattacher le gestionnaire d'événements au nouveau bouton
                        const newAddButton = document.getElementById('addMarchandise');
                        if (newAddButton) {
                            newAddButton.addEventListener('click', ajouterChamp);
                        }
                        
                        // Ajouter un premier champ de marchandise
                        ajouterChamp();
                    }
                });
            }
        },
        preConfirm: () => {
            const form = document.getElementById('sortieForm');
            const formData = new FormData(form);
            
            // Vérifier si retour à vide ou si des marchandises sont renseignées
            const retourVide = document.getElementById('retourVide').checked;
            const marchandises = document.querySelectorAll('.marchandise-item');
            
            if (!retourVide && marchandises.length === 0) {
                Swal.showValidationMessage('Veuillez ajouter au moins une marchandise ou cocher "Retour à vide"');
                return false;
            }
            
            // Récupérer les données du formulaire
            const data = {
                action: 'autoriser_sortie',
                camion_id: camionId,
                retour_vide: retourVide,
                port_destination: formData.get('port_destination'),
                observations: formData.get('observations'),
                marchandises: []
            };

            // Récupérer les données des marchandises
            const marchandiseElements = document.querySelectorAll('.marchandise-item');
            marchandiseElements.forEach(marchandiseEl => {
                const typeId = marchandiseEl.querySelector('select')?.value;
                const inputs = marchandiseEl.querySelectorAll('input[type="number"]');
                const quantiteInput = inputs && inputs.length > 0 ? inputs[0] : null;
                const poidsInput = inputs && inputs.length > 1 ? inputs[1] : null;
                const quantite = quantiteInput ? quantiteInput.value : '';
                const poids = poidsInput ? poidsInput.value : '';
                
                if (typeId && quantite !== '') {
                    data.marchandises.push({
                        type_id: parseInt(typeId, 10),
                        type_marchandise_id: parseInt(typeId, 10),
                        quantite: parseFloat(quantite),
                        poids: poids !== '' ? parseFloat(poids) : null
                    });
                }
            });

            // Vérifier si des marchandises ont été ajoutées (sauf si retour à vide)
            if (!retourVide && data.marchandises.length === 0) {
                Swal.showValidationMessage('Veuillez ajouter au moins une marchandise');
                return false;
            }
            // Valider que le poids est fourni quand non retour à vide
            if (!retourVide) {
                const hasPoids = data.marchandises.some(m => typeof m.poids === 'number' && m.poids > 0);
                if (!hasPoids) {
                    Swal.showValidationMessage('Veuillez saisir le poids total (kg) pour au moins une marchandise');
                    return false;
                }
            }

            return data;
        },
        width: '600px',
        focusConfirm: false,
        didOpen: () => {
            // Configurer le gestionnaire d'événements pour le bouton d'ajout de marchandise
            const addButton = document.getElementById('addMarchandise');
            const container = document.getElementById('marchandisesContainer');
            
            if (addButton && container) {
                addButton.addEventListener('click', () => {
                    if (marchandisesDisponibles && marchandisesDisponibles.length > 0) {
                        ajouterChampMarchandise(container, marchandisesDisponibles);
                    } else {
                        console.error('Aucune marchandise disponible');
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Aucune marchandise disponible. Veuillez réessayer plus tard.',
                            confirmButtonText: 'OK'
                        });
                    }
                });
                
                // Ajouter le premier champ de marchandise si nécessaire
                if (container.querySelectorAll('.marchandise-item').length === 0 && 
                    !document.getElementById('retourVide').checked) {
                    ajouterChampMarchandise(container, marchandisesDisponibles);
                }
            }
            
            // Gérer le changement de l'état du checkbox retour à vide
            const retourVideCheckbox = document.getElementById('retourVide');
            if (retourVideCheckbox) {
                retourVideCheckbox.addEventListener('change', function() {
                    if (!container) return;
                    
                    if (this.checked) {
                        // Vider le conteneur
                        container.innerHTML = `
                            <div class="flex justify-between items-center">
                                <h4 class="text-sm font-medium text-gray-700">Marchandises à exporter</h4>
                                <button type="button" id="addMarchandise"
                                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-plus mr-1"></i> Ajouter une marchandise
                                </button>
                            </div>
                            <p class="text-sm text-gray-500">Aucune marchandise (retour à vide)</p>`;
                        
                        // Réattacher le gestionnaire d'événements au nouveau bouton
                        const newAddButton = document.getElementById('addMarchandise');
                        if (newAddButton) {
                            newAddButton.addEventListener('click', () => {
                                if (marchandisesDisponibles && marchandisesDisponibles.length > 0) {
                                    ajouterChampMarchandise(container, marchandisesDisponibles);
                                }
                            });
                        }
                    } else {
                        // Réinitialiser le conteneur
                        container.innerHTML = `
                            <div class="flex justify-between items-center">
                                <h4 class="text-sm font-medium text-gray-700">Marchandises à exporter</h4>
                                <button type="button" id="addMarchandise"
                                        class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-plus mr-1"></i> Ajouter une marchandise
                                </button>
                            </div>`;
                        
                        // Réattacher le gestionnaire d'événements au nouveau bouton
                        const newAddButton = document.getElementById('addMarchandise');
                        if (newAddButton) {
                            newAddButton.addEventListener('click', () => {
                                if (marchandisesDisponibles && marchandisesDisponibles.length > 0) {
                                    ajouterChampMarchandise(container, marchandisesDisponibles);
                                }
                            });
                        }
                        
                        // Ajouter un premier champ de marchandise
                        ajouterChampMarchandise(container, marchandisesDisponibles);
                    }
                });
            }
        },
        preConfirm: () => {
            const form = document.getElementById('sortieForm');
            const formData = new FormData(form);
            
            // Vérifier le port sélectionné
            const retourVide = document.getElementById('retourVide').checked;
            const marchandises = document.querySelectorAll('.marchandise-item');
            const selectedPort = (formData.get('port_destination') || '').toString().trim();
            if (!selectedPort) {
                Swal.showValidationMessage('Veuillez sélectionner un port de destination');
                return false;
            }
            
            if (!retourVide && marchandises.length === 0) {
                Swal.showValidationMessage('Veuillez ajouter au moins une marchandise ou cocher "Retour à vide"');
                return false;
            }
            
            // Récupérer les données du formulaire
            const data = {
                action: 'autoriser_sortie',
                camion_id: camionId,
                retour_vide: retourVide,
                port_destination: formData.get('port_destination'),
                observations: formData.get('observations'),
                marchandises: []
            };
            
            // Si pas de retour à vide, récupérer les marchandises
            if (!retourVide) {
                let invalid = false;
                marchandises.forEach((item) => {
                    const select = item.querySelector('select');
                    const quantiteInput = item.querySelector('input[name$="[quantite]"]');
                    const poidsInput = item.querySelector('input[name$="[poids]"]');
                    const typeId = select ? (select.value || '').trim() : '';
                    const nom = select && select.options && select.selectedIndex >= 0
                        ? select.options[select.selectedIndex].text.trim()
                        : '';
                    const quantite = quantiteInput ? parseFloat(quantiteInput.value) : NaN;
                    const poids = poidsInput ? parseFloat(poidsInput.value) : NaN;

                    if (!typeId || !nom || isNaN(quantite) || quantite <= 0 || isNaN(poids) || poids <= 0) {
                        invalid = true;
                        return;
                    }

                    data.marchandises.push({
                        type_marchandise_id: parseInt(typeId, 10),
                        type_id: parseInt(typeId, 10),
                        nom,
                        quantite,
                        poids
                    });
                });
                if (invalid) {
                    Swal.showValidationMessage('Veuillez sélectionner un type de marchandise et saisir une quantité (> 0) et un poids total (> 0) pour chaque ligne');
                    return false;
                }
                if (data.marchandises.length === 0) {
                    Swal.showValidationMessage('Veuillez ajouter au moins une marchandise valide');
                    return false;
                }
            }
            
            return data;
        },
        didOpen: () => {
            // Peupler la liste des ports dès l'ouverture du modal
            try { refreshPortsList(); } catch (e) { /* noop */ }
            // Initialisation gérée par initializeInterface()
            
            // Gérer le retour à vide
            document.getElementById('retourVide').addEventListener('change', function() {
                const container = document.getElementById('marchandisesContainer');
                const addButton = document.getElementById('addMarchandise');
                
                if (this.checked) {
                    container.querySelectorAll('.marchandise-item').forEach(item => item.remove());
                    container.insertAdjacentHTML('beforeend', 
                        '<p class="text-sm text-gray-500">Aucune marchandise (retour à vide)</p>');
                    addButton.disabled = true;
                } else {
                    container.querySelector('p')?.remove();
                    addButton.disabled = false;
                    if (container.querySelectorAll('.marchandise-item').length === 0) {
                        ajouterChampMarchandise(container, marchandisesDisponibles);
                    }
                }
            });

            // Raccorder le bouton "Ajouter une marchandise" dans ce didOpen effectif
            const container = document.getElementById('marchandisesContainer');
            const addButton = document.getElementById('addMarchandise');
            if (addButton && container) {
                addButton.onclick = function() {
                    if (Array.isArray(marchandisesDisponibles) && marchandisesDisponibles.length > 0) {
                        ajouterChampMarchandise(container, marchandisesDisponibles);
                    } else {
                        console.error('Aucune marchandise disponible');
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Aucune marchandise disponible. Veuillez réessayer plus tard.',
                            confirmButtonText: 'OK'
                        });
                    }
                };
                // Ajouter un premier champ si pas de retour à vide
                const retourVide = document.getElementById('retourVide');
                if (container.querySelectorAll('.marchandise-item').length === 0 && (!retourVide || !retourVide.checked)) {
                    if (Array.isArray(marchandisesDisponibles) && marchandisesDisponibles.length > 0) {
                        ajouterChampMarchandise(container, marchandisesDisponibles);
                    }
                }
            }
        }
        }).then((result) => {
        if (result.isConfirmed && result.value) {
            const data = result.value;
            
            // Afficher l'indicateur de chargement
            Swal.fire({
                title: 'Traitement en cours...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            try {
                // Envoyer les données au serveur
                fetch('api/autoriser-sortie.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: result.message || 'Opération effectuée avec succès',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Recharge la page après succès
                            window.location.reload();
                        });
                    } else {
                        throw new Error(result.message || 'Une erreur est survenue');
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: error.message || 'Une erreur est survenue lors de la communication avec le serveur',
                        confirmButtonText: 'OK'
                    });
                });
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur',
                    text: 'Une erreur inattendue est survenue',
                    confirmButtonText: 'OK'
                });
            }
        }
    });
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Une erreur inattendue est survenue',
            confirmButtonText: 'OK'
        });
    }
}

function showAutorisationSortie(formContent) {
    Swal.fire({
        title: 'Autorisation de sortie',
        html: formContent,
        showCancelButton: false,
        showConfirmButton: false,
        allowOutsideClick: false,
        width: '80%',
        didOpen: () => {
            const form = document.getElementById('sortieForm');
            // Tenter immédiatement de peupler la liste des ports
            try { refreshPortsList(); } catch (e) { /* noop */ }
            // Charger les données et initialiser l'UI marchandises lorsque le modal est ouvert
            loadInitialData()
                .then(() => {
                    // Rafraîchir la liste des ports du select du formulaire
                    try { refreshPortsList(); } catch (e) { /* noop */ }
                    try { initializeInterface(); } catch (e) { /* noop */ }
                    try { setupMarchandisesHandlers(); } catch (e) { /* noop */ }
                    // Sécurité: rappeler après un court délai pour éviter les courses de timing
                    setTimeout(() => { try { refreshPortsList(); } catch (e) {} }, 250);
                    // Forcer l'attachement du gestionnaire au bouton d'ajout et ajouter un premier champ si nécessaire
                    try {
                        const container = document.getElementById('marchandisesContainer');
                        const addBtn = document.getElementById('addMarchandise');
                        if (container && addBtn) {
                            addBtn.onclick = function() {
                                if (Array.isArray(marchandisesDisponibles) && marchandisesDisponibles.length > 0) {
                                    ajouterChampMarchandise(container, marchandisesDisponibles);
                                } else {
                                    console.error('Aucune marchandise disponible');
                                }
                            };
                            if (container.querySelectorAll('.marchandise-item').length === 0) {
                                if (Array.isArray(marchandisesDisponibles) && marchandisesDisponibles.length > 0) {
                                    ajouterChampMarchandise(container, marchandisesDisponibles);
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('Impossible d\'attacher le gestionnaire addMarchandise:', e);
                    }
                })
                .catch(err => console.error('Erreur chargement données modal:', err));
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(form);
                    
                    // Afficher l'indicateur de chargement
                    Swal.showLoading();
                    
                    // Envoyer les données du formulaire
                    fetch('dashboard.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erreur réseau');
                        }
                        return response.text();
                    })
                    .then(() => {
                        // Recharger la page pour voir les mises à jour
                        window.location.reload();
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Une erreur est survenue lors de l\'enregistrement. Veuillez réessayer.',
                            confirmButtonText: 'OK'
                        });
                    });
                });
            }
        }
    });
}

let marchandisesDisponibles = [];
let portsDisponibles = [];

// Charger et peupler la liste des ports dans le select du formulaire de sortie
function refreshPortsList(targetSelect) {
    const popup = Swal.getPopup && Swal.getPopup();
    const portSelect = targetSelect || (popup ? popup.querySelector('#port_destination') : null) || document.getElementById('port_destination') || document.querySelector('#sortieForm #port_destination');
    if (!portSelect) return;
    // Utiliser d'abord les ports déjà chargés
    if (Array.isArray(portsDisponibles) && portsDisponibles.length > 0) {
        const filtered = portsDisponibles.filter(p => {
            const n = (p.nom || '').toString().trim().toUpperCase();
            return n !== 'BUJUMBURA' && n !== 'PORT DE BUJUMBURA';
        });
        portSelect.innerHTML = '<option value="">Sélectionnez un port</option>' +
            filtered.map(port => `<option value="${port.id}">${port.nom} (${port.pays})</option>`).join('');
        console.debug('Ports affichés depuis cache (portsDisponibles):', portsDisponibles.length);
        return;
    }
    // Sinon, tenter de charger à la volée
    fetch('api/get-ports.php')
        .then(response => {
            if (!response.ok) throw new Error('Erreur lors du chargement des ports');
            return response.json();
        })
        .then(ports => {
            if (Array.isArray(ports)) {
                // Filtrer BUJUMBURA côté client si renvoyé
                portsDisponibles = ports.filter(p => {
                    const n = (p.nom || '').toString().trim().toUpperCase();
                    return n !== 'BUJUMBURA' && n !== 'PORT DE BUJUMBURA';
                });
            }
            console.debug('Ports (refreshPortsList) stockés:', portsDisponibles);
            if (!portsDisponibles || portsDisponibles.length === 0) {
                portSelect.innerHTML = '<option value="">Aucun port disponible</option>';
            } else {
                portSelect.innerHTML = '<option value="">Sélectionnez un port</option>' +
                    portsDisponibles.map(port => `<option value="${port.id}">${port.nom} (${port.pays})</option>`).join('');
            }
            console.debug('Options dans le select ports:', portSelect.options.length);
        })
        .catch(err => {
            console.error('Erreur chargement ports:', err);
        });
}

// Charger les données nécessaires (ports et types de marchandises)
async function loadInitialData() {
    try {
        // Charger les ports (sauf Bujumbura)
        const portsResponse = await fetch('api/get-ports.php');
        if (!portsResponse.ok) {
            throw new Error('Erreur lors du chargement des ports');
        }
        const ports = await portsResponse.json();
        // Conserver les ports (filtrés) pour réutilisation
        if (Array.isArray(ports)) {
            portsDisponibles = ports.filter(p => {
                const n = (p.nom || '').toString().trim().toUpperCase();
                return n !== 'BUJUMBURA' && n !== 'PORT DE BUJUMBURA';
            });
        }
        console.debug('Ports (loadInitialData) stockés:', portsDisponibles);
        
        // Mettre à jour le sélecteur de port dans le modal
        const popup = Swal.getPopup && Swal.getPopup();
        const portSelect = (popup ? popup.querySelector('#port_destination') : null) || document.querySelector('#sortieForm #port_destination');
        if (portSelect) {
            if (!portsDisponibles || portsDisponibles.length === 0) {
                portSelect.innerHTML = '<option value="">Aucun port disponible</option>';
            } else {
                portSelect.innerHTML = '<option value="">Sélectionnez un port</option>' + 
                    portsDisponibles.map(port => 
                        `<option value="${port.id}">${port.nom} (${port.pays})</option>`
                    ).join('');
            }
        }
        
        // Charger les types de marchandises
        const marchandisesResponse = await fetch('api/get-marchandises.php');
        if (!marchandisesResponse.ok) {
            throw new Error('Erreur lors du chargement des marchandises');
        }
        const data = await marchandisesResponse.json();
        
        // S'assurer que les données sont bien un tableau
        if (Array.isArray(data)) {
            marchandisesDisponibles = data;
        } else {
            console.error('Format de données invalide pour les marchandises:', data);
            throw new Error('Format de données invalide pour les marchandises');
        }
        
        // Initialiser l'interface après le chargement des données
        // N'initialiser l'interface que si le formulaire du modal est présent
        const modalForm = document.getElementById('sortieForm');
        if (modalForm) {
            initializeInterface();
        }

    } catch (error) {
        console.error('Erreur lors du chargement des données initiales:', error);
        Swal.fire({
            icon: 'error',
            title: 'Erreur',
            text: 'Impossible de charger les données nécessaires. Veuillez rafraîchir la page. ' + error.message,
            confirmButtonText: 'OK'
        });
    }
}

// Fonction pour initialiser l'interface après le chargement des données
function initializeInterface() {
    // S'assurer que les marchandises sont disponibles avant de configurer les gestionnaires
    if (marchandisesDisponibles && marchandisesDisponibles.length > 0) {
        setupMarchandisesHandlers();
        
        // Ajouter un gestionnaire d'événement pour le bouton d'ajout de marchandise
        const addButton = document.getElementById('addMarchandise');
        const container = document.getElementById('marchandisesContainer');
        
        if (addButton && container) {
            addButton.onclick = function() {
                ajouterChampMarchandise(container, marchandisesDisponibles);
            };
            
            // Ajouter un premier champ si nécessaire
            if (container.querySelectorAll('.marchandise-item').length === 0) {
                ajouterChampMarchandise(container, marchandisesDisponibles);
            }
        }
    } else {
        console.warn('Aucune marchandise disponible pour initialiser l\'interface');
    }
}

// Fonction pour configurer les gestionnaires d'événements des marchandises
function setupMarchandisesHandlers() {
    const addButton = document.getElementById('addMarchandise');
    const container = document.getElementById('marchandisesContainer');
    const retourVideCheckbox = document.getElementById('retourVide');
    
    // Vérifier que les éléments nécessaires existent
    if (!addButton || !container) {
        console.warn('Éléments du formulaire de marchandises non trouvés');
        return;
    }
    
    // Cloner et remplacer le bouton pour éviter les doublons d'écouteurs
    let newAddButton = addButton;
    if (addButton.parentNode) {
        try {
            newAddButton = addButton.cloneNode(true);
            addButton.parentNode.replaceChild(newAddButton, addButton);
        } catch (e) {
            console.warn('Impossible de cloner le bouton d\'ajout:', e);
        }
    }
    
    // Ajouter un nouvel écouteur d'événement
    newAddButton.addEventListener('click', function() {
        if (marchandisesDisponibles && marchandisesDisponibles.length > 0) {
            ajouterChampMarchandise(container, marchandisesDisponibles);
        } else {
            console.error('Aucune marchandise disponible');
        }
    });
    
    // Gérer le retour à vide
    if (retourVideCheckbox && retourVideCheckbox.parentNode) {
        let newCheckbox = retourVideCheckbox;
        try {
            newCheckbox = retourVideCheckbox.cloneNode(true);
            retourVideCheckbox.parentNode.replaceChild(newCheckbox, retourVideCheckbox);
        } catch (e) {
            console.warn('Impossible de cloner la case à cocher retour à vide:', e);
        }
        
        newCheckbox.addEventListener('change', function() {
            const container = document.getElementById('marchandisesContainer');
            if (!container) return;
            
            if (this.checked) {
                // Vider le conteneur
                container.innerHTML = '';
                // Désactiver le bouton d'ajout
                newAddButton.disabled = true;
                // Ajouter un message
                container.insertAdjacentHTML('beforeend', 
                    '<p class="text-sm text-gray-500">Aucune marchandise (retour à vide)</p>');
            } else {
                // Réactiver le bouton d'ajout
                newAddButton.disabled = false;
                // Vérifier si on doit ajouter un champ
                if (container.querySelectorAll('.marchandise-item').length === 0) {
                    ajouterChampMarchandise(container, marchandisesDisponibles);
                }
            }
        });
    }
    
    // Ajouter le premier champ si nécessaire
    if (container && (!retourVideCheckbox || !retourVideCheckbox.checked)) {
        ajouterChampMarchandise(container, marchandisesDisponibles);
    }
}

// Définir des fonctions vides si elles sont manquantes afin d'éviter les erreurs de référence
if (typeof window !== 'undefined') {
    window.setupMobileMenu = window.setupMobileMenu || function() {};
    window.setupCamionNonChargeHandler = window.setupCamionNonChargeHandler || function() {};
    window.initializeTooltips = window.initializeTooltips || function() {};
    window.startApp = window.startApp || function() {};
}

// Initialiser l'application
function initApplication() {
    setupMobileMenu();
    setupCamionNonChargeHandler();
    initializeTooltips();
    
    // Charger les données initiales (ports et marchandises)
    loadInitialData().then(() => {
        console.log('Données initiales chargées avec succès');
    }).catch(error => {
        console.error('Erreur lors du chargement des données initiales:', error);
    });
}

// Vérifier si le DOM est déjà chargé
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApplication);
} else {
    initApplication();
    startApp();
}
