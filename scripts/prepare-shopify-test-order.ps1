param([string]$OutputPath = 'var/shopify-test-order.json')
$ErrorActionPreference = 'Stop'
# Only prepares synthetic input; never authenticates, invokes Shopify or sends a notification.
$items = @(1..30 | ForEach-Object {
    @{
        title = ('Ordely TEST import line {0:D2}' -f $_)
        sku = ('ORDELY-TEST-{0:D2}' -f $_)
        quantity = 1
        priceSet = @{ shopMoney = @{ amount = '1.01'; currencyCode = 'RON' } }
        taxLines = @(@{ title = 'Synthetic tax'; rate = 0.19; priceSet = @{ shopMoney = @{ amount = '0.19'; currencyCode = 'RON' } } })
    }
})
$fixture = @{
    order = @{
        name = 'ORDELY-TEST-M08'
        test = $true
        currency = 'RON'
        presentmentCurrency = 'RON'
        financialStatus = 'PENDING'
        taxesIncluded = $false
        email = 'ordely-import@example.test'
        shippingAddress = @{ firstName = 'Ordely'; lastName = 'Test'; address1 = 'Strada Test 1'; city = 'Bucuresti'; countryCode = 'RO'; zip = '000000' }
        lineItems = $items
        tags = @('ordely-module-08-synthetic')
    }
    options = @{ inventoryBehaviour = 'BYPASS'; sendReceipt = $false; sendFulfillmentReceipt = $false }
}
$fixture | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $OutputPath -Encoding utf8NoBOM
Write-Output 'Synthetic input prepared: 30 lines, test=true, PENDING, no notifications, inventory BYPASS.'
