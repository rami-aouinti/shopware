<script>
export default {
    overrideFrom: 'sw-profile-index-search-preferences',
    // This override is necessary to add the snippets for the search preferences (details) page in the user profile.
    // See route #/sw/profile/index/search-preferences. All entity properties that are part of the module's search
    // configuration should be listed with a respective snippet here.
};
</script>

<i18n>
{
    "de-DE": {
        "sw-profile": {
            "tabSearchPreferences": {
                "modules": {
                    "pickware_erp_warehouse": {
                        "name": "Name",
                        "code": "Kürzel"
                    },
                    "pickware_erp_supplier": {
                        "name": "Name",
                        "number": "Nummer"
                    },
                    "pickware_erp_return_order": {
                        "number": "Nummer"
                    },
                    "pickware_erp_supplier_order": {
                        "number": "Nummer",
                        "name": "Lieferant"
                    },
                    "pickware_erp_goods_receipt": {
                        "number": "Nummer"
                    }
                }
            }
        }
    },
    "en-GB": {
        "sw-profile": {
            "tabSearchPreferences": {
                "modules": {
                    "pickware_erp_warehouse": {
                        "name": "Name",
                        "code": "Code"
                    },
                    "pickware_erp_supplier": {
                        "name": "Name",
                        "number": "Number"
                    },
                    "pickware_erp_return_order": {
                        "number": "Number"
                    },
                    "pickware_erp_supplier_order": {
                        "number": "Number",
                        "name": "Supplier"
                    },
                    "pickware_erp_goods_receipt": {
                        "number": "Number"
                    }
                }
            }
        }
    }
}
</i18n>
