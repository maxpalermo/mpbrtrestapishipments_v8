/**
 * BRT REST API Shipments & Tracking Admin JS
 * Vanilla JS with fetch() using application/x-www-form-urlencoded format.
 */

if (typeof jQuery !== "undefined") {
    jQuery.migrateMute = true;
    jQuery.migrateTrace = false;
}

class BrtParcelManager {
    constructor() {
        this.tbody = null;
        this.parcels = [];
    }

    init() {
        this.tbody = document.getElementById("brt_parcels_table_body");
    }

    reset(defaultWeight = 1.0) {
        this.init();
        this.parcels = [{ progressivo: 1, weight: parseFloat(defaultWeight) || 1.0, x: 0, y: 0, z: 0, volume: 0.0, is_envelope: 0 }];
        this.render();
    }

    setParcels(parcelsList = [], defaultWeight = 1.0) {
        this.init();
        if (Array.isArray(parcelsList) && parcelsList.length > 0) {
            this.parcels = parcelsList.map((p, idx) => ({
                id_weight: p.id_weight || 0,
                progressivo: parseInt(p.progressivo || idx + 1, 10),
                weight: parseFloat(p.weight || 0),
                x: parseFloat(p.x || 0),
                y: parseFloat(p.y || 0),
                z: parseFloat(p.z || 0),
                volume: parseFloat(p.volume || 0),
                is_envelope: parseInt(p.is_envelope || 0, 10),
            }));
        } else {
            this.parcels = [{ progressivo: 1, weight: parseFloat(defaultWeight) || 1.0, x: 0, y: 0, z: 0, volume: 0.0, is_envelope: 0 }];
        }
        this.render();
    }

    addParcelRow(data = {}) {
        this.init();
        const nextProg = this.parcels.length + 1;
        this.parcels.push({
            progressivo: nextProg,
            weight: parseFloat(data.weight || 1.0),
            x: parseFloat(data.x || 0),
            y: parseFloat(data.y || 0),
            z: parseFloat(data.z || 0),
            volume: parseFloat(data.volume || 0),
            is_envelope: parseInt(data.is_envelope || 0, 10),
        });
        this.render();
    }

    removeParcelRow(index) {
        this.init();
        if (this.parcels.length <= 1) {
            alert("È necessario mantenere almeno un pacco per la spedizione.");
            return;
        }
        const itemToRemove = this.parcels[index];
        if (itemToRemove && itemToRemove.id_weight && window.brtModalInstance) {
            window.brtModalInstance.postFetch("deleteParcel", { id_weight: itemToRemove.id_weight }).catch((e) => console.error(e));
        }

        this.parcels.splice(index, 1);
        this.parcels.forEach((p, idx) => (p.progressivo = idx + 1));
        this.render();
    }

    updateRowFromInputs(index) {
        if (!this.tbody) return;
        const row = this.tbody.children[index];
        if (!row) return;

        const weightInput = row.querySelector(".brt-p-weight");
        const xInput = row.querySelector(".brt-p-x");
        const yInput = row.querySelector(".brt-p-y");
        const zInput = row.querySelector(".brt-p-z");
        const volInput = row.querySelector(".brt-p-vol");
        const envInput = row.querySelector(".brt-p-env");

        const weight = parseFloat(weightInput ? weightInput.value : "0") || 0;
        const isEnv = envInput && envInput.checked ? 1 : 0;
        let x = parseFloat(xInput ? xInput.value : "0") || 0;
        let y = parseFloat(yInput ? yInput.value : "0") || 0;
        let z = parseFloat(zInput ? zInput.value : "0") || 0;

        let vol = 0;
        if (!isEnv && x > 0 && y > 0 && z > 0) {
            vol = Math.round(((x * y * z) / 1000000.0) * 10000) / 10000;
        }

        if (volInput) {
            volInput.value = vol > 0 ? vol.toFixed(4) : "0.0000";
        }

        this.parcels[index] = {
            ...this.parcels[index],
            weight: weight,
            x: x,
            y: y,
            z: z,
            volume: vol,
            is_envelope: isEnv,
        };

        this.recalculateGlobalTotals();
    }

    recalculateGlobalTotals() {
        let totalWeight = 0;
        let totalVol = 0;
        const totalCount = this.parcels.length;

        this.parcels.forEach((p) => {
            totalWeight += p.weight || 0;
            totalVol += p.volume || 0;
        });

        const colliEl = document.getElementById("brt_numberOfParcels");
        const weightEl = document.getElementById("brt_weightKG");
        const volEl = document.getElementById("brt_volumeM3");

        if (colliEl) colliEl.value = Math.max(1, totalCount);
        if (weightEl) weightEl.value = Math.max(0.1, totalWeight).toFixed(2);
        if (volEl) volEl.value = totalVol > 0 ? totalVol.toFixed(4) : "0.001";
    }

    render() {
        this.init();
        if (!this.tbody) return;

        this.tbody.innerHTML = "";
        this.parcels.forEach((p, idx) => {
            const tr = document.createElement("tr");
            tr.innerHTML = `
                <td class="align-middle font-weight-bold bg-light">${p.progressivo}</td>
                <td class="align-middle">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-center brt-p-weight" value="${p.weight}" onchange="brtParcelManager.updateRowFromInputs(${idx})" onkeyup="brtParcelManager.updateRowFromInputs(${idx})">
                </td>
                <td class="align-middle">
                    <input type="number" step="0.1" min="0" class="form-control form-control-sm text-center brt-p-x" value="${p.x}" onchange="brtParcelManager.updateRowFromInputs(${idx})" onkeyup="brtParcelManager.updateRowFromInputs(${idx})">
                </td>
                <td class="align-middle">
                    <input type="number" step="0.1" min="0" class="form-control form-control-sm text-center brt-p-y" value="${p.y}" onchange="brtParcelManager.updateRowFromInputs(${idx})" onkeyup="brtParcelManager.updateRowFromInputs(${idx})">
                </td>
                <td class="align-middle">
                    <input type="number" step="0.1" min="0" class="form-control form-control-sm text-center brt-p-z" value="${p.z}" onchange="brtParcelManager.updateRowFromInputs(${idx})" onkeyup="brtParcelManager.updateRowFromInputs(${idx})">
                </td>
                <td class="align-middle">
                    <input type="number" step="0.0001" min="0" class="form-control form-control-sm text-center brt-p-vol" value="${(p.volume || 0).toFixed(4)}" readonly style="background-color:#f8fafc;">
                </td>
                <td class="align-middle">
                    <input type="checkbox" class="brt-p-env" ${p.is_envelope ? "checked" : ""} onchange="brtParcelManager.updateRowFromInputs(${idx})">
                </td>
                <td class="align-middle">
                    <button type="button" class="btn btn-outline-danger btn-sm p-0 px-1" onclick="brtParcelManager.removeParcelRow(${idx})" title="Elimina pacco">
                        <i class="material-icons" style="font-size:14px;vertical-align:middle;">delete</i>
                    </button>
                </td>
            `;
            this.tbody.appendChild(tr);
        });

        this.recalculateGlobalTotals();
    }

    getParcelsData() {
        return this.parcels;
    }
}

window.brtParcelManager = new BrtParcelManager();

class BrtShipmentModal {
    constructor() {
        this.dialog = document.getElementById("brtRestApiDialog");
        this.promptDialog = document.getElementById("brtSelectOrderDialog");
        this.form = document.getElementById("brtShipmentForm");
        this.alertBox = document.getElementById("brtModalAlert");
        this.adminUrl = window.brtAdminUrl || "";
        this.bindEvents();
    }

    bindEvents() {
        const handlePricingEvent = (e) => {
            if (!e.target) return;
            const id = e.target.id || "";
            const name = e.target.name || "";
            const pricingIds = ["brt_network", "brt_serviceType", "brt_deliveryFreightTypeCode", "brt_numberOfParcels", "brt_weightKG", "brt_volumeM3", "brt_consigneeCountryAbbreviationISOAlpha2"];

            if (pricingIds.includes(id) || (name && name.startsWith("create_data["))) {
                this.updatePricingConditionCodeDisplay();
            }

            if (id === "brt_cashOnDelivery" || id === "brt_codPaymentType") {
                this.updateCodPaymentTypeDisplay();
            }
        };

        document.addEventListener("change", handlePricingEvent);
        document.addEventListener("input", handlePricingEvent);
    }

    updateCodPaymentTypeDisplay() {
        const codAmount = parseFloat(document.getElementById("brt_cashOnDelivery")?.value || "0");
        const codTypeSelect = document.getElementById("brt_codPaymentType");
        const displaySpan = document.getElementById("brt_codPaymentType_display");

        if (!displaySpan) return;

        if (codAmount > 0) {
            const val = codTypeSelect ? codTypeSelect.value : "";
            const labels = {
                "": "CA (Contanti)",
                CA: "CA (Contanti)",
                BM: "BM (Ass. Bancario Mitt.)",
                CM: "CM (Ass. Circolare Mitt.)",
                BB: "BB (Ass. Bancario Corriere)",
                OM: "OM (Ass. Mitt. Orig.)",
                OC: "OC (Ass. Circ. Mitt. Orig.)",
            };
            displaySpan.textContent = labels[val] || (val ? `${val} (Contrassegno)` : "CA (Contanti)");
        } else {
            displaySpan.textContent = "Nessuno (€0.00)";
        }
    }

    async updatePricingConditionCodeDisplay(calculatedCode = null) {
        const displaySpan = document.getElementById("brt_pricingConditionCode_display");
        if (!displaySpan) return;

        if (calculatedCode !== null && calculatedCode !== undefined) {
            displaySpan.textContent = calculatedCode || "020";
            return;
        }

        const formEl = document.getElementById("brtShipmentForm");
        if (!formEl) return;

        const formData = new FormData(formEl);
        const params = {};
        for (const [key, value] of formData.entries()) {
            params[key] = value;
        }

        if (window.brtParcelManager && typeof window.brtParcelManager.getParcelsData === "function") {
            const parcelsData = window.brtParcelManager.getParcelsData();
            parcelsData.forEach((p, idx) => {
                params[`create_data[parcels][${idx}][progressivo]`] = p.progressivo;
                params[`create_data[parcels][${idx}][weight]`] = p.weight;
                params[`create_data[parcels][${idx}][x]`] = p.x;
                params[`create_data[parcels][${idx}][y]`] = p.y;
                params[`create_data[parcels][${idx}][z]`] = p.z;
                params[`create_data[parcels][${idx}][volume]`] = p.volume;
                params[`create_data[parcels][${idx}][is_envelope]`] = p.is_envelope;
            });
        }

        try {
            const result = await this.postFetch("evaluatePricingRule", params);
            if (result.success && result.code !== undefined) {
                displaySpan.textContent = result.code || "020";
            }
        } catch (e) {
            console.error("Error evaluating pricing condition code:", e);
        }
    }

    resetForm() {
        if (this.form) {
            this.form.reset();
        }
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        };

        setVal("brt_id_order", 0);
        setVal("brt_search_order_id", "");
        const badgeEl = document.getElementById("brt_order_ref_badge");
        if (badgeEl) badgeEl.innerHTML = "Nuova Spedizione Manuale";
        setVal("brt_consigneeCountryAbbreviationISOAlpha2", "IT");
        setVal("brt_numberOfParcels", 1);
        setVal("brt_weightKG", "1.0");
        setVal("brt_volumeM3", "0.001");
        setVal("brt_cashOnDelivery", "0.00");
        setVal("brt_numericSenderReference", "");
        setVal("brt_alphanumericSenderReference", "");
        const printBtn = document.getElementById("btnBrtPrintLabel");
        if (printBtn) printBtn.disabled = true;
        window.brtParcelManager.reset(1.0);
        this.updateCodPaymentTypeDisplay();
        this.updatePricingConditionCodeDisplay();
        this.clearAlert();
    }

    open(idOrder = 0) {
        if (!this.dialog) {
            this.dialog = document.getElementById("brtRestApiDialog");
        }
        if (!this.dialog) return;

        this.clearAlert();
        if (idOrder > 0) {
            document.getElementById("brt_id_order").value = idOrder;
            document.getElementById("brt_search_order_id").value = idOrder;
            document.getElementById("brt_numericSenderReference").value = idOrder;
            this.loadOrderData(idOrder);
        } else {
            this.resetForm();
        }

        if (typeof this.dialog.showModal === "function") {
            this.dialog.showModal();
        } else {
            this.dialog.setAttribute("open", "true");
        }
    }

    close() {
        if (!this.dialog) return;
        if (typeof this.dialog.close === "function") {
            this.dialog.close();
        } else {
            this.dialog.removeAttribute("open");
        }
    }

    openFromOrderPrompt() {
        if (!this.promptDialog) {
            this.promptDialog = document.getElementById("brtSelectOrderDialog");
        }
        if (!this.promptDialog) return;

        const input = document.getElementById("brt_prompt_order_id");
        if (input) input.value = "";

        if (typeof this.promptDialog.showModal === "function") {
            this.promptDialog.showModal();
        } else {
            this.promptDialog.setAttribute("open", "true");
        }

        if (input) setTimeout(() => input.focus(), 100);
    }

    closeFromOrderPrompt() {
        if (!this.promptDialog) return;
        if (typeof this.promptDialog.close === "function") {
            this.promptDialog.close();
        } else {
            this.promptDialog.removeAttribute("open");
        }
    }

    submitFromOrderPrompt() {
        const input = document.getElementById("brt_prompt_order_id");
        const idOrder = parseInt(input ? input.value : "0", 10);
        if (!idOrder) {
            alert("Inserire un ID ordine valido");
            return;
        }

        this.closeFromOrderPrompt();
        this.open(idOrder);
    }

    showAlert(message, type = "info") {
        if (type === "success") {
            if (typeof showSuccessMessage === "function") {
                showSuccessMessage(message);
            }
            this.clearAlert();
            return;
        }
        if (type === "notice" || type === "info") {
            if (typeof showNoticeMessage === "function") {
                showNoticeMessage(message);
                this.clearAlert();
                return;
            }
        }
        if (type === "error") {
            if (typeof showErrorMessage === "function") {
                showErrorMessage(message);
            }
            this.clearAlert();
            return;
        }

        if (!this.alertBox) return;

        this.alertBox.className = `alert alert-${type}`;
        this.alertBox.innerHTML = message;
        this.alertBox.classList.remove("d-none");
    }

    clearAlert() {
        if (!this.alertBox) return;
        this.alertBox.classList.add("d-none");
        this.alertBox.innerHTML = "";
    }

    /**
     * Helper for fetch requests with urlencoded body.
     */
    async postFetch(action, params = {}) {
        const urlParams = new URLSearchParams();
        urlParams.append("ajax", "1");
        urlParams.append("action", action);

        for (const [key, value] of Object.entries(params)) {
            if (typeof value === "object" && value !== null) {
                for (const [subKey, subVal] of Object.entries(value)) {
                    urlParams.append(`${key}[${subKey}]`, subVal);
                }
            } else {
                urlParams.append(key, value);
            }
        }

        const response = await fetch(this.adminUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
            },
            body: urlParams.toString(),
        });

        return await response.json();
    }

    async loadOrderData(idOrder) {
        if (!idOrder) {
            idOrder = parseInt(document.getElementById("brt_search_order_id").value || "0", 10);
        }
        if (!idOrder) {
            this.showAlert("Inserire un ID ordine valido", "warning");
            return;
        }

        this.showAlert("Caricamento dati ordine in corso...", "info");

        try {
            const result = await this.postFetch("getOrderData", { id_order: idOrder });
            if (result.success && result.data) {
                const d = result.data;
                const setVal = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) el.value = val !== null && val !== undefined ? val : "";
                };

                setVal("brt_id_order", d.id_order);
                const badgeEl = document.getElementById("brt_order_ref_badge");
                if (badgeEl) {
                    badgeEl.innerHTML = `Ord. #${d.id_order} (${d.alphanumericSenderReference || d.reference || ""})`;
                }

                setVal("brt_consigneeCompanyName", d.consigneeCompanyName);
                setVal("brt_consigneeAddress", d.consigneeAddress);
                setVal("brt_consigneeZIPCode", d.consigneeZIPCode);
                setVal("brt_consigneeCity", d.consigneeCity);
                setVal("brt_consigneeProvinceAbbreviation", d.consigneeProvinceAbbreviation);
                setVal("brt_consigneeCountryAbbreviationISOAlpha2", d.consigneeCountryAbbreviationISOAlpha2 || "IT");
                setVal("brt_consigneeTelephone", d.consigneeTelephone);
                setVal("brt_consigneeMobilePhoneNumber", d.consigneeMobilePhoneNumber || d.consigneeMobilePhone);
                setVal("brt_consigneeMobilePhone", d.consigneeMobilePhoneNumber || d.consigneeMobilePhone);
                setVal("brt_consigneeEMail", d.consigneeEMail);
                setVal("brt_consigneeItalianFiscalCode", d.consigneeItalianFiscalCode);

                setVal("brt_cashOnDelivery", d.cashOnDelivery || 0.0);
                setVal("brt_codPaymentType", d.codPaymentType);

                setVal("brt_numericSenderReference", d.numericSenderReference || d.id_order || idOrder);
                setVal("brt_alphanumericSenderReference", d.alphanumericSenderReference || d.reference);
                setVal("brt_network", d.network !== undefined ? d.network : "");

                window.brtParcelManager.setParcels(d.parcels || [], d.weightKG || 1.0);

                const printBtn = document.getElementById("btnBrtPrintLabel");
                if (printBtn) {
                    printBtn.disabled = !d.has_label;
                }

                this.updateCodPaymentTypeDisplay();
                this.updatePricingConditionCodeDisplay(d.pricingConditionCode);
                this.showAlert("Dati ordine e pacchi caricati con successo!", "success");
            } else {
                this.showAlert(result.error || "Impossibile trovare l'ordine specificato", "danger");
            }
        } catch (e) {
            this.showAlert("Errore durante il caricamento dati: " + e.message, "danger");
        }
    }

    async createShipment() {
        const idOrder = parseInt(document.getElementById("brt_id_order").value || "0", 10);
        const formEl = document.getElementById("brtShipmentForm");
        const formData = new FormData(formEl);

        const params = { id_order: idOrder };
        for (const [key, value] of formData.entries()) {
            params[key] = value;
        }

        if (idOrder > 0) {
            if (!params["create_data[numericSenderReference]"] || params["create_data[numericSenderReference]"] === "0") {
                params["create_data[numericSenderReference]"] = idOrder;
            }
            if (!params["create_data[alphanumericSenderReference]"]) {
                const alphaInput = document.getElementById("brt_alphanumericSenderReference");
                if (alphaInput && alphaInput.value) {
                    params["create_data[alphanumericSenderReference]"] = alphaInput.value;
                }
            }
        }

        const parcelsData = window.brtParcelManager.getParcelsData();
        parcelsData.forEach((p, idx) => {
            params[`create_data[parcels][${idx}][progressivo]`] = p.progressivo;
            params[`create_data[parcels][${idx}][weight]`] = p.weight;
            params[`create_data[parcels][${idx}][x]`] = p.x;
            params[`create_data[parcels][${idx}][y]`] = p.y;
            params[`create_data[parcels][${idx}][z]`] = p.z;
            params[`create_data[parcels][${idx}][volume]`] = p.volume;
            params[`create_data[parcels][${idx}][is_envelope]`] = p.is_envelope;
        });

        this.showAlert("Invio richiesta segnacollo a BRT (POST create)...", "info");
        const btnCreate = document.getElementById("btnBrtCreateShipment");
        if (btnCreate) btnCreate.disabled = true;

        try {
            const result = await this.postFetch("createShipment", params);
            if (result.success) {
                this.showAlert("Spedizione creata con successo! Segnacollo generato.", "success");
                const printBtn = document.getElementById("btnBrtPrintLabel");
                if (printBtn) printBtn.disabled = false;
                this.printLabel(null, idOrder);
            } else {
                const errorText = result.error || "Impossibile creare la spedizione BRT";
                this.showAlert("Errore BRT: " + errorText, "danger");
                showBrtAlertModal("Errore Creazione Spedizione BRT", `<div class="alert alert-danger border-danger mb-0"><i class="material-icons mr-1" style="vertical-align:middle;">error</i> <strong>${errorText}</strong></div>`);
            }
        } catch (e) {
            this.showAlert("Errore di comunicazione: " + e.message, "danger");
            showBrtAlertModal("Errore di Comunicazione", `<div class="alert alert-danger border-danger mb-0"><i class="material-icons mr-1" style="vertical-align:middle;">error</i> <strong>Errore di comunicazione:</strong> ${e.message}</div>`);
        } finally {
            if (btnCreate) btnCreate.disabled = false;
        }
    }

    async deleteShipment() {
        const numericRef = parseInt(document.getElementById("brt_numericSenderReference").value || "0", 10);
        const alphanumericRef = document.getElementById("brt_alphanumericSenderReference").value;

        if (!numericRef) {
            this.showAlert("Riferimento numerico mancante per l'annullamento", "warning");
            return;
        }

        if (!confirm("Sei sicuro di voler annullare questa spedizione su BRT (PUT delete)?")) {
            return;
        }

        this.showAlert("Annullamento spedizione in corso (PUT delete)...", "info");

        try {
            const result = await this.postFetch("deleteShipment", {
                numeric_sender_reference: numericRef,
                alphanumeric_sender_reference: alphanumericRef,
            });

            if (result.success) {
                this.showAlert("Spedizione annullata con successo su BRT.", "success");
                showBrtAlertModal("Esito Annullamento BRT", `<div class="alert alert-success border-success mb-0"><i class="material-icons mr-1" style="vertical-align:middle;">check_circle</i> <strong>Spedizione annullata con successo su BRT.</strong></div>`);
            } else {
                const errorText = result.error || "Impossibile annullare la spedizione BRT";
                this.showAlert("Errore annullamento BRT: " + errorText, "danger");
                showBrtAlertModal("Errore Annullamento BRT", `<div class="alert alert-danger border-danger mb-0"><i class="material-icons mr-1" style="vertical-align:middle;">error</i> <strong>${errorText}</strong></div>`);
            }
        } catch (e) {
            this.showAlert("Errore di comunicazione: " + e.message, "danger");
            showBrtAlertModal("Errore di Comunicazione", `<div class="alert alert-danger border-danger mb-0"><i class="material-icons mr-1" style="vertical-align:middle;">error</i> <strong>Errore di comunicazione:</strong> ${e.message}</div>`);
        }
    }

    async printLabel(explicitNumericRef = null, explicitIdOrder = null) {
        const numericRef = explicitNumericRef || parseInt(document.getElementById("brt_numericSenderReference")?.value || "0", 10);
        const idOrder = explicitIdOrder || parseInt(document.getElementById("brt_id_order")?.value || "0", 10);

        if (!numericRef && !idOrder) {
            showBrtAlertModal("Stampa Segnacollo", `<div class="alert alert-warning border-warning mb-0"><i class="material-icons mr-1" style="vertical-align:middle;">warning</i> <strong>Nessun riferimento o ID ordine specificato per la stampa.</strong></div>`);
            return;
        }

        this.showAlert("Recupero ed unione segnacolli PDF in corso...", "notice");

        try {
            const result = await this.postFetch("getLabel", {
                numeric_sender_reference: numericRef,
                id_order: idOrder,
            });

            if (result.success && result.pdf_base64) {
                const byteCharacters = atob(result.pdf_base64);
                const byteNumbers = new Array(byteCharacters.length);
                for (let i = 0; i < byteCharacters.length; i++) {
                    byteNumbers[i] = byteCharacters.charCodeAt(i);
                }
                const byteArray = new Uint8Array(byteNumbers);
                const blob = new Blob([byteArray], { type: "application/pdf" });
                const blobUrl = URL.createObjectURL(blob);

                const win = window.open(blobUrl, "_blank");
                if (!win) {
                    window.location.href = blobUrl;
                }
                this.showAlert(`Segnacollo PDF generato (${result.count || 1} etichetta/e) ed aperto in una nuova scheda.`, "success");
            } else {
                const errorText = result.error || "Nessun segnacollo PDF trovato per la spedizione.";
                this.showAlert("Errore recupero etichetta: " + errorText, "danger");
                showBrtAlertModal("Errore Stampa Segnacollo", `<div class="alert alert-danger border-danger mb-0"><i class="material-icons mr-1" style="vertical-align:middle;">error</i> <strong>${errorText}</strong></div>`);
            }
        } catch (e) {
            this.showAlert("Errore durante il recupero del segnacollo: " + e.message, "danger");
            showBrtAlertModal("Errore Stampa Segnacollo", `<div class="alert alert-danger border-danger mb-0"><i class="material-icons mr-1" style="vertical-align:middle;">error</i> <strong>Errore di comunicazione:</strong> ${e.message}</div>`);
        }
    }

    async viewRequestJson(type = "create") {
        if (type === "delete") {
            const numericRef = parseInt(document.getElementById("brt_numericSenderReference").value || "0", 10);
            const alphanumericRef = document.getElementById("brt_alphanumericSenderReference").value || "";

            const payload = {
                account: {
                    userID: "(Configurato nel modulo)",
                    password: "****************",
                },
                deleteData: {
                    senderCustomerCode: "(Codice Cliente Sandbox/Account)",
                    numericSenderReference: numericRef,
                    alphanumericSenderReference: alphanumericRef,
                },
            };

            showBrtJsonDialog("Corpo della Richiesta DELETE / Annullamento (JSON)", JSON.stringify(payload, null, 2));
            return;
        }

        const idOrder = parseInt(document.getElementById("brt_id_order").value || "0", 10);
        const formEl = document.getElementById("brtShipmentForm");
        if (!formEl) return;

        const formData = new FormData(formEl);
        const params = { id_order: idOrder };
        for (const [key, value] of formData.entries()) {
            params[key] = value;
        }

        const parcelsData = window.brtParcelManager.getParcelsData();
        parcelsData.forEach((p, idx) => {
            params[`create_data[parcels][${idx}][progressivo]`] = p.progressivo;
            params[`create_data[parcels][${idx}][weight]`] = p.weight;
            params[`create_data[parcels][${idx}][x]`] = p.x;
            params[`create_data[parcels][${idx}][y]`] = p.y;
            params[`create_data[parcels][${idx}][z]`] = p.z;
            params[`create_data[parcels][${idx}][volume]`] = p.volume;
            params[`create_data[parcels][${idx}][is_envelope]`] = p.is_envelope;
        });

        try {
            const result = await this.postFetch("previewCreateJson", params);
            if (result.success && result.payload) {
                showBrtJsonDialog("Corpo della Richiesta CREATE (JSON)", JSON.stringify(result.payload, null, 2));
            } else {
                alert(result.error || "Impossibile generare la preview JSON.");
            }
        } catch (e) {
            alert("Errore durante la generazione della preview JSON: " + e.message);
        }
    }
}

function getBrtModalInstance() {
    if (!window.brtModalInstance) {
        try {
            window.brtModalInstance = new BrtShipmentModal();
        } catch (e) {
            console.error("Error initializing BrtShipmentModal:", e);
        }
    }
    return window.brtModalInstance;
}

function brtRestApiOpenDialog(idOrder = 0) {
    const inst = getBrtModalInstance();
    if (inst) inst.open(idOrder);
}
function brtRestApiNewManualShipment() {
    const inst = getBrtModalInstance();
    if (inst) inst.open(0);
}
function brtRestApiNewFromOrder() {
    const inst = getBrtModalInstance();
    if (inst) inst.openFromOrderPrompt();
}
function brtRestApiCloseSelectOrderDialog() {
    const inst = getBrtModalInstance();
    if (inst) inst.closeFromOrderPrompt();
}
function brtRestApiSubmitSelectOrder() {
    const inst = getBrtModalInstance();
    if (inst) inst.submitFromOrderPrompt();
}
function brtRestApiCloseDialog() {
    const inst = getBrtModalInstance();
    if (inst) inst.close();
}
function brtRestApiLoadOrderData() {
    const inst = getBrtModalInstance();
    if (inst) inst.loadOrderData();
}
function brtRestApiCreateShipment() {
    const inst = getBrtModalInstance();
    if (inst) inst.createShipment();
}
function brtRestApiViewRequestJson(type = "create") {
    const inst = getBrtModalInstance();
    if (inst) inst.viewRequestJson(type);
}
function brtRestApiViewDeleteRequestJson() {
    const inst = getBrtModalInstance();
    if (inst) inst.viewRequestJson("delete");
}
function brtRestApiDeleteShipment() {
    const inst = getBrtModalInstance();
    if (inst) inst.deleteShipment();
}
function brtRestApiPrintLabel(explicitNumericRef = null, explicitIdOrder = null) {
    const inst = getBrtModalInstance();
    if (inst) inst.printLabel(explicitNumericRef, explicitIdOrder);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => getBrtModalInstance());
} else {
    getBrtModalInstance();
}

// Auto-teleport #growls inside active HTML5 Top Layer <dialog open>
if (typeof MutationObserver !== "undefined") {
    const teleportGrowlsToDialog = () => {
        const activeDialog = document.querySelector("dialog[open]");
        const growls = document.getElementById("growls") || document.querySelector(".growl")?.parentElement;
        if (activeDialog && growls && growls.parentElement !== activeDialog) {
            activeDialog.appendChild(growls);
        }
    };

    const growlObserver = new MutationObserver(teleportGrowlsToDialog);
    const startObserver = () => {
        if (document.body) {
            growlObserver.observe(document.body, { childList: true, subtree: true });
            teleportGrowlsToDialog();
        }
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", startObserver);
    } else {
        startObserver();
    }
}

class BrtPricingRulesManager {
    constructor() {
        this.tbody = null;
        this.rules = [];
        this.lastFocusedInput = null;
    }

    init() {
        this.tbody = document.getElementById("brt_pricing_rules_body");
        if (window.brtPricingRulesData && Array.isArray(window.brtPricingRulesData) && this.rules.length === 0) {
            this.rules = window.brtPricingRulesData;
        }
    }

    render() {
        this.init();
        if (!this.tbody) return;

        this.tbody.innerHTML = "";
        if (!this.rules || this.rules.length === 0) {
            const tr = document.createElement("tr");
            tr.innerHTML = `<td colspan="4" class="text-muted small py-3">Nessuna regola condizionale configurata. Fai clic su "Aggiungi Regola" per inserirne una.</td>`;
            this.tbody.appendChild(tr);
            return;
        }

        this.rules.forEach((rule, idx) => {
            const tr = document.createElement("tr");

            let exprStr = rule.expression || "";
            if (!exprStr && Array.isArray(rule.conditions)) {
                exprStr = rule.conditions.map((c) => `{${c.field}}${c.operator}"${c.value}"`).join(" AND ");
            }

            const codeVal = rule.pricingConditionCode !== undefined ? rule.pricingConditionCode : "";

            tr.innerHTML = `
                <td class="align-middle font-weight-bold bg-light">${idx + 1}</td>
                <td class="align-middle p-1">
                    <input type="text" class="form-control form-control-sm brt-rule-expr" name="MPBRTRESTAPI_PRICING_RULES[${idx}][expression]" value="${this.escapeAttr(exprStr)}" placeholder='{network}="D" AND {numberOfParcels}="1"' onfocus="brtPricingRules.setLastFocused(this)">
                </td>
                <td class="align-middle p-1">
                    <div class="input-group input-group-sm flex-nowrap" style="max-width:180px; margin:0 auto;">
                        <input type="text" class="form-control form-control-sm text-center font-weight-bold brt-rule-code" name="MPBRTRESTAPI_PRICING_RULES[${idx}][pricingConditionCode]" value="${this.escapeAttr(codeVal)}" placeholder="es. 390">
                        <div class="input-group-append input-group-btn">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="brtPricingRules.setEmptyCode(${idx})" title="Inserisci valore VUOTO">
                                <span class="font-weight-bold" style="font-size:11px;">VUOTO</span>
                            </button>
                        </div>
                    </div>
                </td>
                <td class="align-middle p-1">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary p-0 px-1" onclick="brtPricingRules.moveRow(${idx}, -1)" ${idx === 0 ? "disabled" : ""} title="Sposta su">
                            <i class="material-icons" style="font-size:14px;vertical-align:middle;">arrow_upward</i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary p-0 px-1" onclick="brtPricingRules.moveRow(${idx}, 1)" ${idx === this.rules.length - 1 ? "disabled" : ""} title="Sposta giù">
                            <i class="material-icons" style="font-size:14px;vertical-align:middle;">arrow_downward</i>
                        </button>
                        <button type="button" class="btn btn-outline-danger p-0 px-1" onclick="brtPricingRules.removeRuleRow(${idx})" title="Elimina regola">
                            <i class="material-icons" style="font-size:14px;vertical-align:middle;">delete</i>
                        </button>
                    </div>
                </td>
            `;
            this.tbody.appendChild(tr);
        });
    }

    setEmptyCode(index) {
        this.syncInputsToRules();
        if (this.rules[index]) {
            this.rules[index].pricingConditionCode = "VUOTO";
        }
        this.render();
    }

    setLastFocused(inputEl) {
        this.lastFocusedInput = inputEl;
    }

    insertTag(tagStr) {
        if (!this.lastFocusedInput) {
            if (!this.rules || this.rules.length === 0) {
                this.addRuleRow();
            }
            const inputs = document.querySelectorAll(".brt-rule-expr");
            if (inputs.length > 0) {
                this.lastFocusedInput = inputs[inputs.length - 1];
            }
        }

        if (this.lastFocusedInput) {
            const el = this.lastFocusedInput;
            const start = el.selectionStart !== undefined ? el.selectionStart : el.value.length;
            const end = el.selectionEnd !== undefined ? el.selectionEnd : el.value.length;
            const text = el.value;

            el.value = text.substring(0, start) + tagStr + text.substring(end);
            el.focus();
            if (typeof el.setSelectionRange === "function") {
                el.setSelectionRange(start + tagStr.length, start + tagStr.length);
            }
        }
    }

    addRuleRow(ruleData = {}) {
        this.init();
        this.syncInputsToRules();
        this.rules.push({
            expression: ruleData.expression || "",
            pricingConditionCode: ruleData.pricingConditionCode || "",
            conditions: ruleData.conditions || [],
        });
        this.render();
    }

    removeRuleRow(index) {
        this.init();
        this.syncInputsToRules();
        this.rules.splice(index, 1);
        this.render();
    }

    moveRow(index, direction) {
        this.init();
        this.syncInputsToRules();
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= this.rules.length) return;

        const temp = this.rules[index];
        this.rules[index] = this.rules[targetIndex];
        this.rules[targetIndex] = temp;
        this.render();
    }

    syncInputsToRules() {
        if (!this.tbody) return;
        const exprInputs = this.tbody.querySelectorAll(".brt-rule-expr");
        const codeInputs = this.tbody.querySelectorAll(".brt-rule-code");

        exprInputs.forEach((input, idx) => {
            if (this.rules[idx]) {
                this.rules[idx].expression = input.value;
                if (codeInputs[idx]) {
                    this.rules[idx].pricingConditionCode = codeInputs[idx].value;
                }
            }
        });
    }

    escapeAttr(str) {
        return (str || "").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }

    validateRules() {
        this.init();
        this.syncInputsToRules();

        const exprInputs = document.querySelectorAll(".brt-rule-expr");
        exprInputs.forEach((i) => i.classList.remove("is-invalid", "border-danger"));

        for (let idx = 0; idx < this.rules.length; idx++) {
            const expr = (this.rules[idx].expression || "").trim();
            if (!expr) continue;

            const err = this.validateExpression(expr, idx);
            if (err) {
                this.currentError = err;
                if (exprInputs[idx]) {
                    exprInputs[idx].classList.add("is-invalid", "border-danger");
                    exprInputs[idx].focus();
                }
                this.showSyntaxErrorModal(err);
                return false;
            }
        }

        return true;
    }

    validateExpression(expr, ruleIdx) {
        expr = (expr || "").trim();
        if (!expr) return null;

        const availableFields = {
            network: "network",
            numberofparcels: "numberOfParcels",
            weightkg: "weightKG",
            volumem3: "volumeM3",
            deliveryfreighttypecode: "deliveryFreightTypeCode",
            servicetype: "serviceType",
            consigneecountryabbreviationisoalpha2: "consigneeCountryAbbreviationISOAlpha2",
            consigneeprovinceabbreviation: "consigneeProvinceAbbreviation",
            cashondelivery: "cashOnDelivery",
            sendercustomercode: "senderCustomerCode",
            departuredepot: "departureDepot",
        };

        const validOperators = ["=", "!=", ">", ">=", "<", "<=", "RANGE", "IN", "==", "EQ", "NEQ", "GT", "GTE", "LT", "LTE"];

        // 1. Check brackets balance
        const openBrackets = (expr.match(/\{/g) || []).length;
        const closeBrackets = (expr.match(/\}/g) || []).length;
        if (openBrackets !== closeBrackets) {
            return {
                ruleIndex: ruleIdx,
                expression: expr,
                error: `Parentesi graffe sbilanciate: trovate <strong>${openBrackets}</strong> '{' e <strong>${closeBrackets}</strong> '}'.`,
                suggestion: `Assicurati che ogni campo sia racchiuso tra parentesi graffe, es: <code>{numberOfParcels}</code>.`,
            };
        }

        // 2. Check field names inside {}
        const fieldMatches = expr.match(/\{([^}]+)\}/g);
        if (!fieldMatches || fieldMatches.length === 0) {
            return {
                ruleIndex: ruleIdx,
                expression: expr,
                error: `Nessun campo della spedizione racchiuso tra parentesi graffe <code>{campo}</code> trovato nell'espressione.`,
                suggestion: `Usa un campo valido racchiuso tra parentesi graffe, ad esempio <code>{network}</code> o <code>{numberOfParcels}</code>.`,
            };
        }

        for (let fieldTag of fieldMatches) {
            const fieldName = fieldTag.replace(/[{}]/g, "").trim();
            const lowerName = fieldName.toLowerCase();

            if (!availableFields[lowerName]) {
                const closest = this.findClosestField(fieldName, Object.values(availableFields));
                const fixAction = closest ? `{${closest}}` : null;

                return {
                    ruleIndex: ruleIdx,
                    expression: expr,
                    error: `Il campo <code class="text-danger">${fieldTag}</code> non è un parametro riconosciuto da BRT.`,
                    suggestion: closest ? `Forse intendevi scrivere <code class="text-success font-weight-bold">{${closest}}</code>?<br>Campi validi: <code>{network}</code>, <code>{numberOfParcels}</code>, <code>{weightKG}</code>, <code>{volumeM3}</code>, <code>{deliveryFreightTypeCode}</code>, <code>{serviceType}</code>, <code>{consigneeCountryAbbreviationISOAlpha2}</code>.` : `Campi disponibili: <code>{network}</code>, <code>{numberOfParcels}</code>, <code>{weightKG}</code>, <code>{volumeM3}</code>.`,
                    autoFix: fixAction ? { target: fieldTag, replacement: fixAction } : null,
                };
            }
        }

        // 3. Check conditions and operators
        const condParts = expr.split(/\s+AND\s+/i);
        for (let part of condParts) {
            part = part.trim();
            if (!part) continue;

            const m = part.match(/\{?([a-zA-Z0-9_]+)\}?\s*([^\s"]+)\s*["\']?([^"\']*)["\']?/);
            if (!m) {
                return {
                    ruleIndex: ruleIdx,
                    expression: expr,
                    error: `La condizione <code>"${part}"</code> ha una struttura non valida.`,
                    suggestion: `Il formato corretto per ciascuna condizione è <code>{campo} OPERATORE "valore"</code> (es: <code>{numberOfParcels}="1"</code> o <code>{numberOfParcels} RANGE "2,5"</code>).`,
                };
            }

            const op = m[2].toUpperCase();
            const val = (m[3] || "").trim();

            if (!validOperators.includes(op)) {
                return {
                    ruleIndex: ruleIdx,
                    expression: expr,
                    error: `L'operatore <code>"${op}"</code> nella condizione <code>"${part}"</code> non è supportato.`,
                    suggestion: `Operatori supportati: <code>=</code>, <code>!=</code>, <code>></code>, <code>>=</code>, <code><</code>, <code><=</code>, <code>RANGE</code>, <code>IN</code>.`,
                };
            }

            // 4. Validate RANGE operator syntax
            if (op === "RANGE") {
                if (val.includes("AND") || val.includes("-")) {
                    const cleanVal = val.replace(/AND|-/gi, ",").replace(/\s+/g, "");
                    return {
                        ruleIndex: ruleIdx,
                        expression: expr,
                        error: `L'operatore RANGE richiede la sintassi con virgola <code>"min,max"</code> (trovato: <code>"${val}"</code>).`,
                        suggestion: `Modifica il valore in <code>RANGE "${cleanVal}"</code> senza usare AND o trattini.`,
                        autoFix: { target: `RANGE "${val}"`, replacement: `RANGE "${cleanVal}"` },
                    };
                }

                const rangeParts = val.split(",");
                if (rangeParts.length < 2 || isNaN(parseFloat(rangeParts[0])) || isNaN(parseFloat(rangeParts[1]))) {
                    return {
                        ruleIndex: ruleIdx,
                        expression: expr,
                        error: `Formato RANGE non valido: <code>RANGE "${val}"</code>.`,
                        suggestion: `L'operatore RANGE richiede due numeri separati da virgola, es: <code>RANGE "2,5"</code>.`,
                    };
                }
            }
        }

        return null;
    }

    findClosestField(target, candidates) {
        target = target.toLowerCase();
        let minDistance = 999;
        let bestCandidate = null;

        for (let cand of candidates) {
            let distance = this.levenshteinDistance(target, cand.toLowerCase());
            if (distance < minDistance && distance <= 4) {
                minDistance = distance;
                bestCandidate = cand;
            }
        }
        return bestCandidate;
    }

    levenshteinDistance(a, b) {
        const matrix = [];
        for (let i = 0; i <= b.length; i++) matrix[i] = [i];
        for (let j = 0; j <= a.length; j++) matrix[0][j] = j;

        for (let i = 1; i <= b.length; i++) {
            for (let j = 1; j <= a.length; j++) {
                if (b.charAt(i - 1) === a.charAt(j - 1)) {
                    matrix[i][j] = matrix[i - 1][j - 1];
                } else {
                    matrix[i][j] = Math.min(matrix[i - 1][j - 1] + 1, matrix[i][j - 1] + 1, matrix[i - 1][j] + 1);
                }
            }
        }
        return matrix[b.length][a.length];
    }

    showSyntaxErrorModal(err) {
        const dialog = document.getElementById("brtRuleSyntaxErrorDialog");
        if (!dialog) {
            alert(`Errore di Sintassi nella Regola #${err.ruleIndex + 1}:\n${err.error}`);
            return;
        }

        const titleEl = document.getElementById("brtSyntaxErrorTitle");
        const exprEl = document.getElementById("brtSyntaxErrorExpression");
        const detailEl = document.getElementById("brtSyntaxErrorDetail");
        const suggEl = document.getElementById("brtSyntaxErrorSuggestion");
        const autoFixBtn = document.getElementById("brtBtnAutoFixSyntax");

        if (titleEl) titleEl.textContent = `Errore di Sintassi nella Regola #${err.ruleIndex + 1}`;
        if (exprEl) exprEl.textContent = err.expression;
        if (detailEl) detailEl.innerHTML = err.error;
        if (suggEl) suggEl.innerHTML = err.suggestion;

        if (autoFixBtn) {
            autoFixBtn.style.display = err.autoFix ? "inline-flex" : "none";
        }

        if (typeof dialog.showModal === "function") {
            dialog.showModal();
        } else {
            dialog.setAttribute("open", "");
        }
    }

    applyAutoFix() {
        if (!this.currentError || !this.currentError.autoFix) return;

        const idx = this.currentError.ruleIndex;
        const fix = this.currentError.autoFix;
        if (this.rules[idx]) {
            this.rules[idx].expression = this.rules[idx].expression.replace(fix.target, fix.replacement);
            this.render();
        }

        const dialog = document.getElementById("brtRuleSyntaxErrorDialog");
        if (dialog && typeof dialog.close === "function") {
            dialog.close();
        }
    }
}

window.brtPricingRules = new BrtPricingRulesManager();

function showBrtAlertModal(title, htmlContent) {
    let dialog = document.getElementById("brtGeneralAlertDialog");
    if (!dialog) {
        dialog = document.createElement("dialog");
        dialog.id = "brtGeneralAlertDialog";
        dialog.className = "bootstrap border-0 p-0 rounded shadow-lg";
        dialog.style.cssText = "max-width: 550px; width: 90%; font-family: inherit;";
        dialog.innerHTML = `
            <form method="dialog" class="m-0" onsubmit="event.preventDefault();">
                <div class="modal-header bg-primary text-white py-2 px-3 align-items-center">
                    <h5 class="modal-title font-weight-bold text-white mb-0" id="brtGeneralAlertTitle" style="font-size:1.1rem;"></h5>
                    <button type="button" class="close text-white opacity-100" onclick="document.getElementById('brtGeneralAlertDialog').close();">&times;</button>
                </div>
                <div class="modal-body p-4 bg-light" id="brtGeneralAlertBody"></div>
                <div class="modal-footer bg-white py-2 px-3 text-right">
                    <button type="button" class="btn btn-secondary btn-sm font-weight-bold" onclick="document.getElementById('brtGeneralAlertDialog').close();">Chiudi</button>
                </div>
            </form>
        `;
        document.body.appendChild(dialog);
    }
    document.getElementById("brtGeneralAlertTitle").textContent = title;
    document.getElementById("brtGeneralAlertBody").innerHTML = htmlContent;

    if (typeof dialog.showModal === "function") {
        dialog.showModal();
    } else {
        dialog.setAttribute("open", "");
    }
}

function showBrtJsonDialog(title, jsonString) {
    let dialog = document.getElementById("brtJsonViewerDialog");
    if (!dialog) {
        dialog = document.createElement("dialog");
        dialog.id = "brtJsonViewerDialog";
        dialog.className = "bootstrap border-0 p-0 rounded shadow-lg";
        dialog.style.cssText = "max-width: 750px; width: 92%; font-family: inherit;";
        dialog.innerHTML = `
            <form method="dialog" class="m-0" onsubmit="event.preventDefault();">
                <div class="modal-header bg-dark text-white py-2 px-3 align-items-center justify-content-between">
                    <h5 class="modal-title font-weight-bold text-white mb-0 d-flex align-items-center gap-2" style="font-size:1.1rem;">
                        <i class="material-icons text-info">code</i>
                        <span id="brtJsonViewerTitle"></span>
                    </h5>
                    <button type="button" class="close text-white opacity-100" onclick="document.getElementById('brtJsonViewerDialog').close();">&times;</button>
                </div>
                <div class="modal-body p-3 bg-dark">
                    <pre class="m-0 p-3 bg-black text-success rounded font-weight-bold" id="brtJsonViewerCode" style="max-height:450px; overflow-y:auto; font-size:12px; white-space:pre-wrap; word-break:break-all; font-family:monospace;"></pre>
                </div>
                <div class="modal-footer bg-light py-2 px-3 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-info btn-sm font-weight-bold d-inline-flex align-items-center gap-1" onclick="navigator.clipboard.writeText(document.getElementById('brtJsonViewerCode').textContent); showBrtAlertModal('JSON Copiato', '<div class=\\'alert alert-success border-success mb-0\\'>JSON della richiesta copiato negli appunti!</div>');">
                        <i class="material-icons" style="font-size:14px;">content_copy</i>
                        <span>Copia JSON negli appunti</span>
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm font-weight-bold" onclick="document.getElementById('brtJsonViewerDialog').close();">Chiudi</button>
                </div>
            </form>
        `;
        document.body.appendChild(dialog);
    }
    document.getElementById("brtJsonViewerTitle").textContent = title;
    document.getElementById("brtJsonViewerCode").textContent = jsonString;

    if (typeof dialog.showModal === "function") {
        dialog.showModal();
    } else {
        dialog.setAttribute("open", "");
    }
}

const initSettingsPage = () => {
    window.brtPricingRules.render();
    const formSettings = document.getElementById("form-brt-settings");
    if (formSettings) {
        formSettings.addEventListener("submit", (e) => {
            if (!window.brtPricingRules.validateRules()) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
    }

    const btnTestBrt = document.getElementById("btn-test-brt");
    if (btnTestBrt) {
        btnTestBrt.addEventListener("click", async () => {
            btnTestBrt.disabled = true;
            const originalHtml = btnTestBrt.innerHTML;
            btnTestBrt.innerHTML = `<i class="material-icons spin" style="font-size:16px;vertical-align:middle;animation: spin 1s linear infinite;">sync</i> Connessione in corso...`;

            try {
                const response = await fetch((window.brtAdminUrl || "") + "&action=TestBrtApi&ajax=1", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                });
                const result = await response.json();

                if (result.success) {
                    showBrtAlertModal("Test Connessione BRT", `<div class="alert alert-success border-success mb-0"> <strong>${result.message}</strong></div>`);
                } else {
                    showBrtAlertModal("Test Connessione BRT", `<div class="alert alert-danger border-danger mb-0"> <strong>${result.error || "Errore durante la connessione."}</strong></div>`);
                }
            } catch (e) {
                showBrtAlertModal("Test Connessione BRT", `<div class="alert alert-danger border-danger mb-0"> <strong>Errore di comunicazione:</strong> ${e.message}</div>`);
            } finally {
                btnTestBrt.disabled = false;
                btnTestBrt.innerHTML = originalHtml;
            }
        });
    }

    document.querySelectorAll(".btn-get-label").forEach((btn) => {
        btn.addEventListener("click", (e) => {
            e.preventDefault();
            const numericRef = btn.getAttribute("data-numeric");
            if (numericRef) {
                window.brtModalInstance.printLabel(numericRef);
            }
        });
    });

    document.querySelectorAll(".btn-delete-shipment").forEach((btn) => {
        btn.addEventListener("click", (e) => {
            e.preventDefault();
            const numericRef = btn.getAttribute("data-numeric");
            if (numericRef) {
                const numInput = document.getElementById("brt_numericSenderReference");
                if (numInput) numInput.value = numericRef;
                window.brtModalInstance.deleteShipment();
            }
        });
    });

    if (document.getElementById("brt_bordero_register_card")) {
        window.brtBorderoPagination = new BrtBorderoPaginationManager();
    }
};

class BrtBorderoPaginationManager {
    constructor() {
        this.currentPage = 1;
        this.limit = 50;
        this.total = 0;
        this.totalPages = 1;
        this.datePrinted = '';
        this.adminUrl = window.brtAdminUrl || '';
        this.adminUrlOrders = '';
        this.init();
    }

    init() {
        const select = document.getElementById('brtBorderoPageSize');
        if (select) {
            this.limit = parseInt(select.value || '50', 10);
        }
        const dateInput = document.getElementById('brtBorderoDatePrintedSearch');
        if (dateInput) {
            this.datePrinted = dateInput.value || '';
        }
        const paginationList = document.getElementById('brtBorderoPaginationList');
        if (paginationList) {
            this.currentPage = parseInt(paginationList.getAttribute('data-current-page') || '1', 10);
            this.totalPages = parseInt(paginationList.getAttribute('data-total-pages') || '1', 10);
        }
        this.renderPaginationControls();
    }

    searchByDatePrinted(dateStr) {
        this.datePrinted = (dateStr || '').trim();
        this.loadPage(1);
    }

    async loadPage(page = 1) {
        if (page < 1) page = 1;
        if (this.totalPages > 0 && page > this.totalPages) page = this.totalPages;
        this.currentPage = page;

        const overlay = document.getElementById('brtBorderoLoadingOverlay');
        if (overlay) {
            overlay.classList.remove('d-none');
            overlay.classList.add('d-flex');
        }

        try {
            let url = `${this.adminUrl}&action=getBorderoShipments&page=${page}&limit=${this.limit}&ajax=1`;
            if (this.datePrinted) {
                url += `&date_printed=${encodeURIComponent(this.datePrinted)}`;
            }
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();

            if (result.success) {
                this.total = result.total || 0;
                this.totalPages = result.total_pages || 1;
                this.currentPage = result.page || 1;
                this.limit = result.limit || 50;
                if (result.admin_url_orders) {
                    this.adminUrlOrders = result.admin_url_orders;
                }

                this.renderRows(result.data || []);
                this.updatePaginationInfo();
                this.renderPaginationControls();
            } else {
                console.error('Error fetching bordero shipments:', result);
            }
        } catch (e) {
            console.error('AJAX fetch error for bordero shipments:', e);
        } finally {
            if (overlay) {
                overlay.classList.remove('d-flex');
                overlay.classList.add('d-none');
            }
        }
    }

    changeLimit(newLimit) {
        this.limit = parseInt(newLimit, 10) || 50;
        this.loadPage(1);
    }

    renderRows(records) {
        const tbody = document.getElementById('brtBorderoTableBody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!records || records.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="12" class="text-center py-4 text-muted">
                        <i class="material-icons d-block mb-1" style="font-size:32px">local_shipping</i>
                        Nessuna spedizione trovata nel registro del borderò.
                    </td>
                </tr>
            `;
            return;
        }

        const adminUrlOrders = this.adminUrlOrders || (window.location.origin + window.location.pathname.replace('AdminMpBrtRestApiShipments', 'AdminOrders'));

        records.forEach((ship) => {
            const tr = document.createElement('tr');

            const orderUrl = (ship.id_order && parseInt(ship.id_order, 10) > 0)
                ? (adminUrlOrders.includes('999999999')
                    ? adminUrlOrders.replace('999999999', ship.id_order)
                    : `${adminUrlOrders}&id_order=${ship.id_order}&vieworder`)
                : '#';

            const orderCell = ship.id_order && parseInt(ship.id_order, 10) > 0
                ? `<a href="${orderUrl}" target="_blank" class="font-weight-bold">#${ship.id_order}</a>`
                : `<span class="text-muted">—</span>`;

            const rangeCell = ship.parcel_number_from
                ? `<span class="badge badge-info">${this.escapeHtml(ship.parcel_number_from)} → ${this.escapeHtml(ship.parcel_number_to)}</span>`
                : `<span class="badge badge-secondary">${ship.number_of_parcels || 1} colli</span>`;

            const codAmount = parseFloat(ship.cash_on_delivery || '0');
            const codCell = codAmount > 0
                ? `<span class="badge badge-warning">€ ${codAmount.toFixed(2).replace('.', ',')}</span>`
                : `<span class="text-muted">-</span>`;

            let printedCell = '';
            if (ship.is_printed && parseInt(ship.is_printed, 10) === 1) {
                printedCell = `<div><span class="badge badge-success" title="Stampato il ${this.escapeHtml(ship.date_printed || '')}"><i class="material-icons" style="font-size:12px">check_circle</i> Stampato</span></div>${ship.date_printed ? `<div style="font-family:monospace;font-size:11px;color:#6c757d;margin-top:2px;">${this.escapeHtml(ship.date_printed)}</div>` : ''}`;
            } else {
                printedCell = `<div><span class="badge badge-warning"><i class="material-icons" style="font-size:12px">schedule</i> Da Stampare</span></div>`;
                if (ship.execution_message && ship.execution_code !== undefined && parseInt(ship.execution_code, 10) !== 0) {
                    const shortMsg = ship.execution_message.length > 35 ? ship.execution_message.substring(0, 35) + '...' : ship.execution_message;
                    printedCell += `<div style="font-size:11px;color:#dc3545;margin-top:2px;" title="${this.escapeHtml(ship.execution_message)}"><i class="material-icons" style="font-size:11px;vertical-align:middle;">error_outline</i> ${this.escapeHtml(shortMsg)}</div>`;
                } else if (!ship.parcel_number_from) {
                    printedCell += `<div style="font-size:11px;color:#fd7e14;margin-top:2px;" title="Segnacollo non generato"><i class="material-icons" style="font-size:11px;vertical-align:middle;">warning</i> Segnacollo mancante</div>`;
                }
            }

            const viewBtn = (ship.id_order && parseInt(ship.id_order, 10) > 0)
                ? `<a href="${orderUrl}" target="_blank" class="btn btn-outline-primary btn-sm mr-1" title="Vedi ordine in una nuova scheda"><i class="material-icons">visibility</i></a>`
                : '';

            tr.innerHTML = `
                <td>${ship.id_brt_restapi_bordero}</td>
                <td>${orderCell}</td>
                <td><code>${this.escapeHtml(ship.numeric_sender_reference || '')}</code></td>
                <td>${this.escapeHtml(ship.alphanumeric_sender_reference || '')}</td>
                <td>${this.escapeHtml(ship.consignee_company_name || '')}</td>
                <td>${this.escapeHtml(ship.consignee_city || '')}</td>
                <td>${rangeCell}</td>
                <td class="text-right">${parseFloat(ship.weight_kg || '0').toFixed(2)} kg</td>
                <td class="text-right">${codCell}</td>
                <td class="text-center">${printedCell}</td>
                <td><small class="text-muted">${this.escapeHtml(ship.date_add || '')}</small></td>
                <td class="text-right">
                    ${viewBtn}
                    <button class="btn btn-outline-info btn-sm btn-get-label" data-numeric="${this.escapeHtml(ship.numeric_sender_reference || '')}" title="Stampa etichetta segnacollo">
                        <i class="material-icons">print</i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm btn-delete-shipment" data-numeric="${this.escapeHtml(ship.numeric_sender_reference || '')}" title="Annulla spedizione BRT">
                        <i class="material-icons">delete</i>
                    </button>
                </td>
            `;

            tbody.appendChild(tr);
        });

        this.bindRowActions();
    }

    bindRowActions() {
        document.querySelectorAll('#brtBorderoTableBody .btn-get-label').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const numericRef = btn.getAttribute('data-numeric');
                if (numericRef && window.brtModalInstance) {
                    window.brtModalInstance.printLabel(numericRef);
                }
            });
        });

        document.querySelectorAll('#brtBorderoTableBody .btn-delete-shipment').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const numericRef = btn.getAttribute('data-numeric');
                if (numericRef && window.brtModalInstance) {
                    const numInput = document.getElementById('brt_numericSenderReference');
                    if (numInput) numInput.value = numericRef;
                    window.brtModalInstance.deleteShipment();
                }
            });
        });
    }

    updatePaginationInfo() {
        const headerBadge = document.getElementById('brtBorderoHeaderBadge');
        if (headerBadge) {
            headerBadge.textContent = `${this.total} record`;
        }

        const infoSpan = document.getElementById('brtBorderoPaginationInfo');
        if (infoSpan) {
            const startCount = this.total > 0 ? (this.currentPage - 1) * this.limit + 1 : 0;
            let endCount = this.currentPage * this.limit;
            if (endCount > this.total) endCount = this.total;
            infoSpan.innerHTML = `Mostrati <strong>${startCount}</strong> - <strong>${endCount}</strong> di <strong>${this.total}</strong> record`;
        }
    }

    renderPaginationControls() {
        const container = document.getElementById('brtBorderoPaginationList');
        if (!container) return;

        container.innerHTML = '';

        if (this.totalPages <= 1) return;

        // Previous button
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${this.currentPage <= 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); brtBorderoPagination.loadPage(${this.currentPage - 1})"><i class="material-icons" style="font-size:14px;vertical-align:middle;">chevron_left</i></a>`;
        container.appendChild(prevLi);

        // Page numbers
        const maxPagesToShow = 5;
        let startPage = Math.max(1, this.currentPage - 2);
        let endPage = Math.min(this.totalPages, startPage + maxPagesToShow - 1);
        if (endPage - startPage < maxPagesToShow - 1) {
            startPage = Math.max(1, endPage - maxPagesToShow + 1);
        }

        if (startPage > 1) {
            const firstLi = document.createElement('li');
            firstLi.className = 'page-item';
            firstLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); brtBorderoPagination.loadPage(1)">1</a>`;
            container.appendChild(firstLi);

            if (startPage > 2) {
                const ellipsisLi = document.createElement('li');
                ellipsisLi.className = 'page-item disabled';
                ellipsisLi.innerHTML = `<span class="page-link">…</span>`;
                container.appendChild(ellipsisLi);
            }
        }

        for (let p = startPage; p <= endPage; p++) {
            const li = document.createElement('li');
            li.className = `page-item ${p === this.currentPage ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); brtBorderoPagination.loadPage(${p})">${p}</a>`;
            container.appendChild(li);
        }

        if (endPage < this.totalPages) {
            if (endPage < this.totalPages - 1) {
                const ellipsisLi = document.createElement('li');
                ellipsisLi.className = 'page-item disabled';
                ellipsisLi.innerHTML = `<span class="page-link">…</span>`;
                container.appendChild(ellipsisLi);
            }

            const lastLi = document.createElement('li');
            lastLi.className = 'page-item';
            lastLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); brtBorderoPagination.loadPage(${this.totalPages})">${this.totalPages}</a>`;
            container.appendChild(lastLi);
        }

        // Next button
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${this.currentPage >= this.totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); brtBorderoPagination.loadPage(${this.currentPage + 1})"><i class="material-icons" style="font-size:14px;vertical-align:middle;">chevron_right</i></a>`;
        container.appendChild(nextLi);
    }

    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initSettingsPage);
} else {
    initSettingsPage();
}
