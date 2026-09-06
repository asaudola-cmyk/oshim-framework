# 👑 OSHIM Sovereign C Engine

> **The Native C-Engine & Bare-Metal Zend Runtime for Next-Generation Computing**

[![OSHIM Core](https://img.shields.io/badge/OSHIM-Sovereign%20Engine-00f2fe?style=for-the-badge)](https://github.com/asaudola-cmyk/oshim-framework)
[![C Native](https://img.shields.io/badge/C-Zend%20Engine%20VM-blue?style=for-the-badge)](https://github.com/asaudola-cmyk/oshim-framework)
[![Zero Middleware](https://img.shields.io/badge/Zero-Middleware-success?style=for-the-badge)](https://github.com/asaudola-cmyk/oshim-framework)

---

## 🏛️ The Paradigm Shift

Traditional web frameworks (Laravel, Symfony, Express, Next.js) run **on top of** third-party runtimes and web servers (Nginx, Apache, PHP-FPM, Node.js). They are trapped within user-land scripting limitations.

**OSHIM is no longer a user-land framework. OSHIM IS THE RUNTIME.**

By embedding the **Zend Virtual Machine** directly into our dedicated C SAPI (`sapi/oshim/`), OSHIM executes code directly on bare-metal hardware:
* **Zero Nginx / Zero Apache / Zero FPM:** Direct POSIX socket event loop in native C.
* **Direct C SAPI Execution:** Direct pointer memory mapping with zero-copy stream buffers.
* **Sovereign C Core:** Built directly from the official Zend Engine & PHP C source code.

---

## 📂 Core Engine Structure

```
oshim-framework/
├── Zend/               # The Core Zend VM (Bytecode Compiler, Executor, Memory Manager)
├── main/               # Main PHP Core API & Streams
├── sapi/
│   ├── oshim/          # 👑 Sovereign OSHIM Native C SAPI (Direct Hardware Driver)
│   ├── cli/            # Standard CLI fallback
│   └── ...
├── ext/                # Core C Extensions (Standard, JSON, FFI, Fiber, Hash, etc.)
└── TSRM/               # Thread-Safe Resource Manager
```

---

## 🚀 Building the Sovereign Engine

### Requirements
* GCC / Clang
* Make
* Linux / POSIX environment

### Compilation
```bash
# 1. Build configuration with Sovereign OSHIM SAPI enabled
./buildconf --force

# 2. Configure native engine
./configure --enable-oshim --enable-fiber --with-ffi

# 3. Compile bare-metal binary
make -j$(nproc)

# 4. Run directly via OSHIM SAPI
./sapi/oshim/oshim app.php
```

---

## 📜 License
The OSHIM Engine is distributed under the [PHP License v3.01](LICENSE).
