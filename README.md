# MariaDB PHP Driver Benchmarks: `executemany`

his repository contains performance benchmarks for the MariaDB PHP RFC, comparing the new `mysqli_stmt::executemany()` method against traditional `execute()` loops and `LOAD DATA LOCAL INFILE`.

## Introdution ##

It is impossible to say that `"executemany()` is 'n-times' faster than a sequential `execute()`" loop as a universal rule. The performance delta is highly dependent on two primary factors: hardware processing power and network latency."

For this reason, two distinct scenarios were chosen for this analysis to demonstrate the impact of these factors:

1. **Localhost:** A very fast connection via `unix_socket`, representing minimal overhead and peak hardware throughput.
2. **Raspberry Pi 4:** A resource-constrained client connected via Ethernet, representing a real-world remote hardware scenario where network latency and CPU limitations typically bottleneck database operations.

## Environment Specifications

To ensure reproducibility, the following hardware and software configurations were used:

### Server Node (192.168.10.1)
* **OS:** Linux (64-bit)
* **Database:** MariaDB 11.8.7
* **CPU:** Intel(R) Core(TM) i7-13700HX (16 Cores / 24 Threads)
* **RAM:** 32GiB DDR5
* **Storage:** 1TB Samsung MZVL21T0HDLU NVMe SSD (PCIe 4.0)

### Remote Client Node (192.168.10.23)
* **Hardware:** Raspberry Pi 4 Model B (Broadcom BCM2711, Quad-core Cortex-A72)
* **RAM:** 4GB LPDDR4 (with 2GB zram enabled)
* **Storage:** 64GB MicroSD card via USB Adapter (Mass Storage Mode)
* **Network:** Gigabit Ethernet (Connected to 10.1)

---

## Benchmark Results

### 1. Local Comparison (Server & Client on same machine)

| Operation | Method | Result |
| :--- | :--- | :--- |
| **Bulk Import** | `LOAD DATA LOCAL INFILE` | 1.3230s |
| **Bulk Import** | `executemany()` (stream) | **1.3236s** |
| **INSERT** | `executemany()` | **740,103 rows/sec** |
| **INSERT** | `execute()` loop | 83,165 rows/sec |
| **UPDATE** | `executemany()` | 182,057 rows/sec |
| **UPDATE** | `execute()` loop | 73,294 rows/sec |
| **DELETE** | `executemany()` | 213,617 rows/sec |
| **DELETE** | `execute()` loop | 74,263 rows/sec |

### 2. Remote Comparison (Client 192.168.10.23 → Server 192.168.10.1)
This test highlights the efficiency of `executemany()` in networked environments where round-trip latency usually cripples performance.

| Operation | Method | Result | Improvement |
| :--- | :--- | :--- | :--- |
| **INSERT** | `executemany()` | **588,859 rows/sec** | **~106x Faster** |
| **INSERT** | `execute()` loop | 5,555 rows/sec | Reference |
| **UPDATE** | `executemany()` | **153,462 rows/sec** | **~38x Faster** |
| **UPDATE** | `execute()` loop | 4,027 rows/sec | Reference |
| **DELETE** | `executemany()` | **207,079 rows/sec** | **~55x Faster** |
| **DELETE** | `execute()` loop | 3,719 rows/sec | Reference |

---

## Key Findings

### Eliminating Network Latency
The most significant result is seen on the Raspberry Pi client. While the standard `execute()` loop performance collapsed from **83,165** to **5,555** rows/sec (a 93% drop) due to network overhead, `executemany()` maintained a high throughput of nearly **600,000** rows/sec. This highlights how `executemany()` effectively negates the "ping-pong" effect of traditional row-by-row execution.

### Performance Parity with LOAD DATA
In local testing on the i7-13700HX, `executemany()` using a stream performed within **0.0006s** of the native `LOAD DATA LOCAL INFILE` command. This proves that the RFC provides a "native-speed" bulk loading experience directly through the PHP API without requiring specific file system permissions or external file handling.

### Architectural Efficiency
Despite the significantly lower clock speed of the Cortex-A72 (ARM) compared to the i7 (x86), the `executemany()` implementation shows minimal performance degradation on the client side. By batching data into fewer, larger packets, the driver maximizes the available bandwidth of the Gigabit Ethernet connection while minimizing CPU interrupts on the client.
