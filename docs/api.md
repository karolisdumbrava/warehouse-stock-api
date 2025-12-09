# Stock Allocation API

## Authentication

All endpoints require `X-API-KEY` header.
```bash
curl -H "X-API-KEY: your-api-key" http://localhost:8080/api/products
```

## Endpoints

### Products

#### List Products
```
GET /api/products
```

Response:
```json
[
    {"id": 1, "sku": "BOX-S", "name": "Small Shipping Box"},
    {"id": 2, "sku": "BOX-M", "name": "Medium Shipping Box"}
]
```

### Warehouses

#### List Warehouses
```
GET /api/warehouses
```

#### Get Warehouse Details
```
GET /api/warehouses/{id}
```

Response:
```json
{
    "id": 1,
    "name": "Vilnius Warehouse",
    "location": "Vilnius, Lithuania",
    "stocks": [
        {
            "product_sku": "BOX-S",
            "product_name": "Small Shipping Box",
            "quantity": 100,
            "reserved": 30,
            "available": 70
        }
    ]
}
```

### Orders

#### Create Order
```
POST /api/orders
Content-Type: application/json

{
    "items": {
        "BOX-S": 10,
        "BOX-M": 5
    }
}
```

Response (201):
```json
{
    "success": true,
    "fully_allocated": true,
    "warehouses_used": 1,
    "missing_items": {}
}
```

#### Get Order
```
GET /api/orders/{id}
```

#### Ship Order
```
POST /api/orders/{id}/ship
```

#### Cancel Order
```
POST /api/orders/{id}/cancel
```

## Error Responses

### 401 Unauthorized
```json
{"error": "Missing API key. Include X-API-KEY header."}
```

### 404 Not Found
```json
{"error": "Order not found"}
```

### 422 Unprocessable Entity
```json
{"error": "Cannot perform this action on the order"}
```
