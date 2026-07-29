# Nutemplete 🍃

[![PHP Version](https://img.shields.io/badge/PHP-%5E8.1%20%7C%20%5E8.2%20%7C%20%5E8.3-blue.svg)](https://php.net)
[![Version](https://img.shields.io/badge/Nutemplete-v3.0.0-success.svg)](LICENSE)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

**Nutemplete** adalah Template Engine PHP modern, super ringan, dan fleksibel buatan Nufat.id. Mengombinasikan kemudahan sintaks PHP murni, direktif **Blade-style**, serta komponen HTML kustom (`<nu-component>`).

---

## 🌟 Fitur Utama (v3.0.0)

- ⚡ **Super Fast & Lightweight:** Performa tinggi tanpa overhead.
- ⚔️ **Blade Directives:** Mendukung direktif `@extends`, `@section`, `@yield`, `@if`, `@foreach`, `@unless`, `@auth`, `@guest`, `@json`, `@asset`, dan `@flash('success')`.
- 🧩 **HTML Components (`<nu-*>`):** Sintaks tag HTML komponen kustom seperti `<nu-card title="Halo">Konten</nu-card>`.
- 🖼️ **Native Layout Blocks:** Dukungan inheritance layout berbasis `$this->extend()` dan `$this->block()`.
- 📱 **QR Code Helper:** Terintegrasi dengan QR Code generator bawaan.

---

## 💻 Cara Instalasi

```bash
composer require nufat/nutemplete
```

---

## 📖 Cara Penggunaan

### 1. Inisialisasi Environment

```php
use Nufat\Nutemplete\Render;

$renderer = new Render(__DIR__ . '/views', '.nu.php');

// Output template
echo $renderer->render('home', [
    'title' => 'Nutemplete Modern',
    'users' => [
        ['name' => 'Baim', 'role' => 'Admin'],
        ['name' => 'Nurani', 'role' => 'Assistant']
    ]
]);
```

### 2. Sintaks Layout Blade-Style (`views/layout.nu.php`)

```html
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'Nutemplete App')</title>
</head>
<body>
    <header>
        <h1>Aplikasi Web</h1>
    </header>

    <main>
        @yield('content')
    </main>
</body>
</html>
```

### 3. Child Template (`views/home.nu.php`)

```html
@extends('layout')

@section('title', $title)

@section('content')
    <h2>Daftar Pengguna</h2>

    @foreach($users as $user)
        <p>{{ $user['name'] }} - {{ $user['role'] }}</p>
    @endforeach
@endsection
```

### 4. HTML Custom Component Tag (`<nu-card>`)

Buat berkas komponen di `resource/components/card.nu.php`:

```html
<div class="card">
    <div class="card-header">{{ $title }}</div>
    <div class="card-body">
        {!! $slot !!}
    </div>
</div>
```

Gunakan di tampilan apapun:

```html
<nu-card title="Pengumuman">
    <p>Ini adalah isi komponen card secara otomatis!</p>
</nu-card>
```

---

## 📜 Lisensi

[MIT License](LICENSE) - Dibuat oleh [Nufat.id](https://webdev.nufat.id)
