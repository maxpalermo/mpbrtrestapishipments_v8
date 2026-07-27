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
    }

    resetForm() {
        if (this.form) {
            this.form.reset();
        }
        document.getElementById("brt_id_order").value = 0;
        document.getElementById("brt_search_order_id").value = "";
        document.getElementById("brt_order_ref_badge").innerHTML = "Nuova Spedizione Manuale";
        document.getElementById("brt_consigneeCountryAbbreviationISOAlpha2").value = "IT";
        document.getElementById("brt_numberOfParcels").value = 1;
        document.getElementById("brt_weightKG").value = "1.0";
        document.getElementById("brt_volumeM3").value = "0.001";
        document.getElementById("brt_cashOnDelivery").value = "0.00";
        document.getElementById("brt_numericSenderReference").value = Math.floor(Date.now() / 1000);
        document.getElementById("btnBrtPrintLabel").disabled = true;
        window.brtParcelManager.reset(1.0);
        this.clearAlert();
    }

    open(idOrder = 0) {
        if (!this.dialog) {
            this.dialog = document.getElementById("brtRestApiDialog");
        }
        if (!this.dialog) return;

        this.clearAlert();
        if (idOrder > 0) {
            document.getElementById("brt_search_order_id").value = idOrder;
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
                document.getElementById("brt_id_order").value = d.id_order;
                document.getElementById("brt_order_ref_badge").innerHTML = `Ord. #${d.id_order} (${d.alphanumericSenderReference})`;

                document.getElementById("brt_consigneeCompanyName").value = d.consigneeCompanyName || "";
                document.getElementById("brt_consigneeAddress").value = d.consigneeAddress || "";
                document.getElementById("brt_consigneeZIPCode").value = d.consigneeZIPCode || "";
                document.getElementById("brt_consigneeCity").value = d.consigneeCity || "";
                document.getElementById("brt_consigneeProvinceAbbreviation").value = d.consigneeProvinceAbbreviation || "";
                document.getElementById("brt_consigneeCountryAbbreviationISOAlpha2").value = d.consigneeCountryAbbreviationISOAlpha2 || "IT";
                document.getElementById("brt_consigneeTelephone").value = d.consigneeTelephone || "";
                const mobileEl = document.getElementById("brt_consigneeMobilePhoneNumber") || document.getElementById("brt_consigneeMobilePhone");
                if (mobileEl) {
                    mobileEl.value = d.consigneeMobilePhoneNumber || d.consigneeMobilePhone || "";
                }
                document.getElementById("brt_consigneeEMail").value = d.consigneeEMail || "";
                document.getElementById("brt_consigneeItalianFiscalCode").value = d.consigneeItalianFiscalCode || "";

                document.getElementById("brt_cashOnDelivery").value = d.cashOnDelivery || 0.0;
                document.getElementById("brt_codPaymentType").value = d.codPaymentType || "";

                document.getElementById("brt_numericSenderReference").value = d.numericSenderReference || idOrder;
                document.getElementById("brt_alphanumericSenderReference").value = d.alphanumericSenderReference || "";

                window.brtParcelManager.setParcels(d.parcels || [], d.weightKG || 1.0);

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
                showBrtAlertModal("Esito Creazione Spedizione BRT", `<div class="alert alert-success border-success mb-0"><i class="material-icons mr-1" style="vertical-align:middle;">check_circle</i> <strong>Spedizione creata con successo su BRT! Segnacollo generato.</strong></div>`);
                const printBtn = document.getElementById("btnBrtPrintLabel");
                if (printBtn) printBtn.disabled = false;
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

    async printLabel(explicitNumericRef = null) {
        const numericRef = explicitNumericRef || parseInt(document.getElementById("brt_numericSenderReference").value || "0", 10);
        const idOrder = parseInt(document.getElementById("brt_id_order").value || "0", 10);

        if (!numericRef && !idOrder) {
            showBrtAlertModal("Stampa Segnacollo", `<div class="alert alert-warning border-warning mb-0"><i class="material-icons mr-1" style="vertical-align:middle;">warning</i> <strong>Nessun riferimento o ID ordine specificato per la stampa.</strong></div>`);
            return;
        }

        this.showAlert("Recupero ed unione segnacolli PDF in corso...", "info");

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

    viewRequestJson() {
        const formEl = document.getElementById("brtShipmentForm");
        if (!formEl) return;

        const formData = new FormData(formEl);
        const createData = {};

        for (const [key, value] of formData.entries()) {
            if (key.startsWith("create_data[")) {
                const fieldName = key.replace(/^create_data\[/, "").replace(/\]$/, "");
                createData[fieldName] = value;
            }
        }

        createData.isAlertRequired = "1";

        const codAmount = parseFloat(createData.cashOnDelivery || "0");
        if (codAmount > 0) {
            createData.cashOnDelivery = codAmount.toFixed(2);
            createData.isCODMandatory = "1";
            createData.codPaymentType = (createData.codPaymentType === "CA") ? "" : (createData.codPaymentType || "");
            createData.codCurrency = "EUR";
        } else {
            delete createData.cashOnDelivery;
            delete createData.isCODMandatory;
            delete createData.codPaymentType;
            delete createData.codCurrency;
        }

        delete createData.parcels;

        const payload = {
            account: {
                userID: "(Configurato nel modulo)",
                password: "****************",
            },
            createData: createData,
            isLabelRequired: "1",
            labelParameters: {
                outputType: "PDF",
                offsetX: "0",
                offsetY: "0",
            },
        };

        const formattedJson = JSON.stringify(payload, null, 2);
        showBrtJsonDialog("Corpo della Richiesta CREATE (JSON)", formattedJson);
    }
}

// Global instance
window.brtModalInstance = new BrtShipmentModal();

function brtRestApiOpenDialog(idOrder = 0) {
    window.brtModalInstance.open(idOrder);
}
function brtRestApiNewManualShipment() {
    window.brtModalInstance.open(0);
}
function brtRestApiNewFromOrder() {
    window.brtModalInstance.openFromOrderPrompt();
}
function brtRestApiCloseSelectOrderDialog() {
    window.brtModalInstance.closeFromOrderPrompt();
}
function brtRestApiSubmitSelectOrder() {
    window.brtModalInstance.submitFromOrderPrompt();
}
function brtRestApiCloseDialog() {
    window.brtModalInstance.close();
}
function brtRestApiLoadOrderData() {
    window.brtModalInstance.loadOrderData();
}
function brtRestApiCreateShipment() {
    window.brtModalInstance.createShipment();
}
function brtRestApiViewRequestJson() {
    window.brtModalInstance.viewRequestJson();
}
function brtRestApiDeleteShipment() {
    window.brtModalInstance.deleteShipment();
}
function brtRestApiPrintLabel() {
    window.brtModalInstance.printLabel();
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
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initSettingsPage);
} else {
    initSettingsPage();
}
