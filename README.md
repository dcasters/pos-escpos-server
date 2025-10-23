# 🖨️ Kiosk Print Server

<div align="center">

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%207.4-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Platform](https://img.shields.io/badge/platform-Windows%20%7C%20Linux-lightgrey)
![Status](https://img.shields.io/badge/status-Production%20Ready-success)

**Enterprise-grade WebSocket print server for ESC/POS thermal printers**

Real-time printing solution with logo support, auto-reconnect, and Windows Service integration.

[Features](#-features) • [Installation](#-installation) • [Usage](#-usage) • [API](#-api-documentation) • [Troubleshooting](#-troubleshooting)

</div>

---

## 📖 Overview

**Kiosk Print Server** is an enterprise printing solution for web-based Point of Sale (POS) self-service systems. The
server uses **WebSocket protocol** (port 12000) for real-time communication with ESC/POS thermal printers.

### ✨ Key Features

- ✅ **WebSocket Real-time** - Bidirectional communication via port 12000
- ✅ **Logo Printing** - Auto-download, cache, and print logos using RAW ESC/POS
- ✅ **Auto-reconnect** - Client reconnection mechanism
- ✅ **Multi-printer Support** - Windows, Network, Linux device support
- ✅ **Windows Service** - Run as background service with auto-start
- ✅ **Production Ready** - Tested at KOPKAR SIWS Self-Service Kiosk

---

## 🚀 Quick Start

### Clone repository

- git clone https://github.com/dcasters/pos-escpos-server.git
- cd ultimatepos-escpos-server/pos_print_server

### Install dependencies

- composer install

#### Start server

- php server.php

---

## 📋 Requirements

| Component      | Version                     |
|----------------|-----------------------------|
| **PHP**        | >= 7.4 (8.1+ recommended)   |
| **Extensions** | `sockets`, `gd`, `mbstring` |
| **Composer**   | Latest stable               |
| **Memory**     | 128MB minimum               |

### PHP Extensions Check

- php -m | grep -E "sockets|gd|mbstring"

### Connection Types:

- `windows` - Windows printer name (e.g., "POS58")
- `network` - Network printer via IP (`ip_address` & `port` required)
- `linux` - USB/Serial device path (e.g., "/dev/usb/lp0")

---

## 💻 Usage

### Basic Print Job (JavaScript)

```
// Connect to print server
const socket = new WebSocket('ws://localhost:6441');

socket.onopen = () => {
const printJob = {
action: 'print',
data: {
logo: 'https://example.com/logo.png',
header_text: 'Your Store Name',
display_name: 'Your Display Location',
address: 'Your Address Here',
invoice_no_prefix: 'INV',
invoice_no: '12345',
invoice_date: '23/10/2025',
lines: [
{
name: 'GAZERO',
variation: '',
quantity: '1',
unit_price_exc_tax: '3.0',
line_total: '3.0'
}
],
subtotal: '3.0',
total: '3.0',
total_paid: '3.0',
footer_text: 'Thank You'
}
};

socket.send(JSON.stringify(printJob));
};

socket.onmessage = (event) => {
const response = JSON.parse(event.data);
console.log('Print result:', response);
};
```

### Print with Auto-cached Logo

Logos are automatically downloaded and cached in the `logos/` folder:

**Cache behavior:**

- First print: Download & cache logo
- Subsequent prints: Use cached version
- Optimization: Auto-resize to 200px width, grayscale conversion

---

## 🪟 Windows Service Setup

### Method 1: NSSM (Recommended)

**Download NSSM:** https://nssm.cc/download


---

## 📡 API Documentation

### WebSocket Endpoint

### Message Format

**Request:**
`{
"action": "print|status|test",
"data": { /* print data */ }
}`

**Response:**
`{
"status": "success|error",
"message": "Print job completed",
"data": {}
}`

### Complete Print Data Structure

`{
"action": "print",
"data": {
"logo": "https://example.com/logo.png",
"header_text": "Store Name",
"display_name": "Location Name",
"address": "Store Address",
"invoice_no_prefix": "INV",
"invoice_no": "12345",
"invoice_date": "DD/MM/YYYY",
"date_label": "Date",
"customer_label": "Customer",
"customer_info": "Customer Name",
"table_qty_label": "Qty",
"table_product_label": "Product",
"table_unit_price_label": "Price",
"table_subtotal_label": "Subtotal",
"lines": [
{
"name": "Product Name",
"variation": "Size/Color",
"quantity": "1",
"unit_price_exc_tax": "10000",
"line_total": "10000",
"sell_line_note": "Optional note",
"sub_sku": "SKU123",
"brand": "Brand Name",
"cat_code": "CAT01"
}
],
"subtotal_label": "Subtotal",
"subtotal": "Rs 10,000",
"discount_label": "Discount",
"discount": "0",
"tax_label": "Tax",
"tax": "0",
"total_label": "Total",
"total": "Rs 10,000",
"total_paid_label": "Paid",
"total_paid": "Rs 10,000",
"total_due_label": "Change",
"total_due": "0",
"footer_text": "Thank You!",
"cash_drawer": false
}
}`

### Connection Issues

- ✅ Check firewall allows port 6641
- ✅ Verify server is running: `netstat -ano | findstr ":6641"`
- ✅ Client connects to `ws://localhost:6641` (not `http://`)
- ✅ Check browser console for WebSocket errors

---

### Key Dependencies

- **mike42/escpos-php** - ESC/POS printer library
- **cboden/ratchet** - WebSocket server framework

### Printer Compatibility

Tested with ESC/POS compatible printers:

- ✅ POS58 (58mm thermal)
- ✅ POS80 (80mm thermal)
- ✅ Epson TM series
- ✅ Star Micronics
- ✅ Generic ESC/POS printers

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

---

## 📝 License

This project is licensed under the **MIT License**.

