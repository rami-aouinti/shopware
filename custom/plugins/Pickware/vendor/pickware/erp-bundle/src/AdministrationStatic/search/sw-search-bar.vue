<script>
export default {
    overrideFrom: 'sw-search-bar',

    methods: {
        async createdComponent() {
            this.searchTypeService.upsertType(
                'pickware_erp_warehouse',
                {
                    entityName: 'pickware_erp_warehouse',
                    placeholderSnippet: 'sw-search-bar.pickware_erp_warehouse.placeholder',
                    listingRoute: 'pw.erp.warehouse.index',
                },
            );
            this.searchTypeService.upsertType(
                'pickware_erp_supplier',
                {
                    entityName: 'pickware_erp_supplier',
                    placeholderSnippet: 'sw-search-bar.pickware_erp_supplier.placeholder',
                    listingRoute: 'pw.erp.supplier.index',
                },
            );
            this.searchTypeService.upsertType(
                'pickware_erp_supplier_order',
                {
                    entityName: 'pickware_erp_supplier_order',
                    placeholderSnippet: 'sw-search-bar.pickware_erp_supplier_order.placeholder',
                    listingRoute: 'pw.erp.supplier.order.index',
                },
            );
            this.searchTypeService.upsertType(
                'pickware_erp_goods_receipt',
                {
                    entityName: 'pickware_erp_goods_receipt',
                    placeholderSnippet: 'sw-search-bar.pickware_erp_goods_receipt.placeholder',
                    listingRoute: 'pw.erp.goods.receipt.index',
                },
            );
            this.searchTypeService.upsertType(
                'pickware_erp_return_order',
                {
                    entityName: 'pickware_erp_return_order',
                    placeholderSnippet: 'sw-search-bar.pickware_erp_return_order.placeholder',
                    listingRoute: 'pw.erp.return.order.list',
                },
            );
            this.searchTypeService.upsertType(
                'pickware_erp_batch',
                {
                    entityName: 'pickware_erp_batch',
                    placeholderSnippet: 'sw-search-bar.pickware_erp_batch.placeholder',
                    listingRoute: 'pw.erp.batch.index',
                },
            );
            await this.$super('createdComponent');
        },
    },
};
</script>

<i18n>
{
    "de-DE": {
        "sw-search-bar": {
            "pickware_erp_supplier": {
                "placeholder": "Durchsuche alle Lieferanten"
            },
            "pickware_erp_warehouse": {
                "placeholder": "Durchsuche alle Lager"
            },
            "pickware_erp_return_order": {
                "placeholder": "Durchsuche alle Retouren"
            },
            "pickware_erp_supplier_order": {
                "placeholder": "Durchsuche alle Lieferantenbestellungen"
            },
            "pickware_erp_goods_receipt": {
                "placeholder": "Durchsuche alle Wareneingänge"
            },
            "pickware_erp_batch": {
                "placeholder": "Durchsuche alle Chargen"
            }
        },
        "global": {
            "entities": {
                "pickware_erp_supplier": "Lieferanten",
                "pickware_erp_warehouse": "Lager",
                "pickware_erp_return_order": "Retouren",
                "pickware_erp_supplier_order": "Lieferantenbestellungen",
                "pickware_erp_goods_receipt": "Wareneingänge",
                "pickware_erp_batch": "Chargen"
            }
        }
    },
    "en-GB": {
        "sw-search-bar": {
            "pickware_erp_supplier": {
                "placeholder": "Search suppliers"
            },
            "pickware_erp_warehouse": {
                "placeholder": "Search warehouses"
            },
            "pickware_erp_return_order": {
                "placeholder": "Search returns"
            },
            "pickware_erp_supplier_order": {
                "placeholder": "Search supplier orders"
            },
            "pickware_erp_goods_receipt": {
                "placeholder": "Search goods receipts"
            },
            "pickware_erp_batch": {
                "placeholder": "Search batches"
            }
        },
        "global": {
            "entities": {
                "pickware_erp_supplier": "Suppliers",
                "pickware_erp_warehouse": "Warehouses",
                "pickware_erp_return_order": "Returns",
                "pickware_erp_supplier_order": "Supplier Orders",
                "pickware_erp_goods_receipt": "Goods Receipts",
                "pickware_erp_batch": "Batches"
            }
        }
    }
}
</i18n>
