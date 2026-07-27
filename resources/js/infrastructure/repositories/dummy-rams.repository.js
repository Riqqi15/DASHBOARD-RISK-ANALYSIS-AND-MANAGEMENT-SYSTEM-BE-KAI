/**
 * @fileoverview Repositori dummy untuk data RAMS (Risk Analysis and Management System).
 * Data statis persis dari file Excel KAI:
 * - Risk Analysis And Management System RAMS Daop 1.xlsm (sheet "Predictive Data Asset")
 * - Risk Analysis And Management System RAMS Divre IV.xlsm
 * Sesuai Dokumen Database RAMS FINAL.
 */

import { AssetModel } from '@/domain/models/asset.model';
import { RiskMatrixModel, RiskRegisterModel } from '@/domain/models/risk-matrix.model';
import { ReliabilityModel, FailureLogModel } from '@/domain/models/reliability.model';
import { UnitKerjaModel } from '@/domain/models/unit-kerja.model';

const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

// ═══════════════════════════════════════════════
// UNIT KERJA
// ═══════════════════════════════════════════════
const unitKerjaData = [
  new UnitKerjaModel({ id: 1, kode: 'PUSAT', nama: 'Kantor Pusat', tipe: 'Pusat', status: 'Aktif' }),
  new UnitKerjaModel({ id: 2, kode: 'DAOP1', nama: 'Daop 1 Jakarta', tipe: 'Daop', status: 'Aktif' }),
  new UnitKerjaModel({ id: 3, kode: 'DAOP2', nama: 'Daop 2 Bandung', tipe: 'Daop', status: 'Aktif' }),
  new UnitKerjaModel({ id: 4, kode: 'DAOP3', nama: 'Daop 3 Cirebon', tipe: 'Daop', status: 'Aktif' }),
  new UnitKerjaModel({ id: 5, kode: 'DAOP4', nama: 'Daop 4 Semarang', tipe: 'Daop', status: 'Aktif' }),
  new UnitKerjaModel({ id: 6, kode: 'DAOP5', nama: 'Daop 5 Purwokerto', tipe: 'Daop', status: 'Aktif' }),
  new UnitKerjaModel({ id: 7, kode: 'DAOP6', nama: 'Daop 6 Yogyakarta', tipe: 'Daop', status: 'Aktif' }),
  new UnitKerjaModel({ id: 8, kode: 'DAOP7', nama: 'Daop 7 Madiun', tipe: 'Daop', status: 'Aktif' }),
  new UnitKerjaModel({ id: 9, kode: 'DAOP8', nama: 'Daop 8 Surabaya', tipe: 'Daop', status: 'Aktif' }),
  new UnitKerjaModel({ id: 10, kode: 'DAOP9', nama: 'Daop 9 Jember', tipe: 'Daop', status: 'Aktif' }),
  new UnitKerjaModel({ id: 11, kode: 'DIVRE1', nama: 'Divre I Sumatera Utara', tipe: 'Divre', status: 'Aktif' }),
  new UnitKerjaModel({ id: 12, kode: 'DIVRE2', nama: 'Divre II Sumatera Barat', tipe: 'Divre', status: 'Aktif' }),
  new UnitKerjaModel({ id: 13, kode: 'DIVRE3', nama: 'Divre III Palembang', tipe: 'Divre', status: 'Aktif' }),
  new UnitKerjaModel({ id: 14, kode: 'DIVRE4', nama: 'Divre IV Tanjungkarang', tipe: 'Divre', status: 'Aktif' })
];

// ═══════════════════════════════════════════════
// MASTER ASET — Persis dari Excel "Predictive Data Asset"
// ═══════════════════════════════════════════════
let assetId = 0;
const assetData = [
  // ══════════════════════════════════════════════════════
  // DAOP 1 JAKARTA — Persis dari file: Risk Analysis And Management System RAMS Daop 1.xlsm
  // ══════════════════════════════════════════════════════

  // 1. PERALATAN DALAM SINYAL ELEKTRIK
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '1. PERALATAN DALAM SINYAL ELEKTRIK', system: 'INTERLOCKING ELEKTRIK', subsystem: 'INTERLOCKING ELEKTRIK', lokasi: 'Daop 1 Jakarta', jumlah_unit: 2, tahun_pemasangan: '2018-01-01', status: 'Aktif' }),
  
  // 2. PERALATAN LUAR SINYAL ELEKTRIK
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'PERAGA SINYAL ELEKTRIK UTAMA', lokasi: 'Daop 1 Jakarta', jumlah_unit: 51, tahun_pemasangan: '2015-06-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'PERAGA SINYAL ELEKTRIK PEMBANTU', lokasi: 'Daop 1 Jakarta', jumlah_unit: 12, tahun_pemasangan: '2015-06-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'PERAGA SINYAL ELEKTRIK PELENGKAP', lokasi: 'Daop 1 Jakarta', jumlah_unit: 0, tahun_pemasangan: '2015-06-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'PENGGERAK WESEL ELEKTRIK', lokasi: 'Daop 1 Jakarta', jumlah_unit: 63, tahun_pemasangan: '2016-03-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'Track Circuit', lokasi: 'Daop 1 Jakarta', jumlah_unit: 81, tahun_pemasangan: '2018-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'Axle Counter', lokasi: 'Daop 1 Jakarta', jumlah_unit: 119, tahun_pemasangan: '2019-05-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'PENGAMAN WESEL SETEMPAT ELEKTRIK', lokasi: 'Daop 1 Jakarta', jumlah_unit: 0, tahun_pemasangan: '2017-08-01', status: 'Aktif' }),

  // 3. PERALATAN DALAM SINYAL MEKANIK
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '3. PERALATAN DALAM SINYAL MEKANIK', system: 'INTERLOCKING MEKANIK', subsystem: 'INTERLOCKING MEKANIK', lokasi: 'Daop 1 Jakarta', jumlah_unit: 0, tahun_pemasangan: '1990-01-01', status: 'Aktif' }),

  // 4. PERALATAN LUAR SINYAL MEKANIK
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'PERAGA SINYAL MEKANIK', subsystem: 'PERAGA SINYAL MEKANIK UTAMA', lokasi: 'Daop 1 Jakarta', jumlah_unit: 0, tahun_pemasangan: '1992-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'PERAGA SINYAL MEKANIK', subsystem: 'PERAGA SINYAL MEKANIK PEMBANTU', lokasi: 'Daop 1 Jakarta', jumlah_unit: 0, tahun_pemasangan: '1992-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'PERAGA SINYAL MEKANIK', subsystem: 'PERAGA SINYAL MEKANIK PELENGKAP', lokasi: 'Daop 1 Jakarta', jumlah_unit: 0, tahun_pemasangan: '1992-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'PENGGERAK WESEL MEKANIK', subsystem: 'PENGGERAK WESEL MEKANIK', lokasi: 'Daop 1 Jakarta', jumlah_unit: 0, tahun_pemasangan: '1995-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'PENGONTROL DAN PETUNJUK KEDUDUKAN WESEL MEKANIK', subsystem: 'PENGONTROL DAN PETUNJUK KEDUDUKAN WESEL MEKANIK', lokasi: 'Daop 1 Jakarta', jumlah_unit: 0, tahun_pemasangan: '1995-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'PENGAMAN WESEL SETEMPAT MEKANIK', subsystem: 'PENGAMAN WESEL SETEMPAT MEKANIK', lokasi: 'Daop 1 Jakarta', jumlah_unit: 0, tahun_pemasangan: '1995-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'KONTAK DETEKSI', subsystem: 'KONTAK DETEKSI', lokasi: 'Daop 1 Jakarta', jumlah_unit: 0, tahun_pemasangan: '2005-01-01', status: 'Aktif' }),

  // 5. CATU DAYA SINTEL
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DAOP1', aset_prasarana_sintel: '5. CATU DAYA SINTEL', system: 'CATU DAYA SINYAL', subsystem: 'CATU DAYA SINYAL', lokasi: 'Daop 1 Jakarta', jumlah_unit: 3, tahun_pemasangan: '2019-01-01', status: 'Aktif' }),

  // ══════════════════════════════════════════════════════
  // DIVRE IV TANJUNGKARANG — Persis dari screenshot Excel sebelumnya
  // ══════════════════════════════════════════════════════
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '1. PERALATAN DALAM SINYAL ELEKTRIK', system: 'INTERLOCKING ELEKTRIK', subsystem: 'INTERLOCKING ELEKTRIK', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 1, tahun_pemasangan: '2018-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'PERAGA SINYAL ELEKTRIK UTAMA', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 7, tahun_pemasangan: '2015-06-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'PERAGA SINYAL ELEKTRIK PEMBANTU', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 23, tahun_pemasangan: '2015-06-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'PERAGA SINYAL ELEKTRIK PELENGKAP', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 9, tahun_pemasangan: '2015-06-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'PENGGERAK WESEL ELEKTRIK', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 10, tahun_pemasangan: '2016-03-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'Track Circuit', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 13, tahun_pemasangan: '2018-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'Axle Counter', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 12, tahun_pemasangan: '2019-05-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '2. PERALATAN LUAR SINYAL ELEKTRIK', system: 'PERAGA SINYAL ELEKTRIK', subsystem: 'PENGAMAN WESEL SETEMPAT ELEKTRIK', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 11, tahun_pemasangan: '2017-08-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '3. PERALATAN DALAM SINYAL MEKANIK', system: 'INTERLOCKING MEKANIK', subsystem: 'INTERLOCKING MEKANIK', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 19, tahun_pemasangan: '1990-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'PERAGA SINYAL MEKANIK', subsystem: 'PERAGA SINYAL MEKANIK UTAMA', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 22, tahun_pemasangan: '1992-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'PERAGA SINYAL MEKANIK', subsystem: 'PERAGA SINYAL MEKANIK PEMBANTU', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 23, tahun_pemasangan: '1992-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'PERAGA SINYAL MEKANIK', subsystem: 'PERAGA SINYAL MEKANIK PELENGKAP', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 24, tahun_pemasangan: '1992-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'PENGGERAK WESEL MEKANIK', subsystem: 'PENGGERAK WESEL MEKANIK', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 25, tahun_pemasangan: '1995-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'PENGONTROL DAN PETUNJUK KEDUDUKAN WESEL MEKANIK', subsystem: 'PENGONTROL DAN PETUNJUK KEDUDUKAN WESEL MEKANIK', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 26, tahun_pemasangan: '1995-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'PENGAMAN WESEL SETEMPAT MEKANIK', subsystem: 'PENGAMAN WESEL SETEMPAT MEKANIK', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 27, tahun_pemasangan: '1995-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '4. PERALATAN LUAR SINYAL MEKANIK', system: 'KONTAK DETEKSI', subsystem: 'KONTAK DETEKSI', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 28, tahun_pemasangan: '2005-01-01', status: 'Aktif' }),
  new AssetModel({ id: ++assetId, unit_kerja_id: 'DIVRE4', aset_prasarana_sintel: '5. CATU DAYA SINTEL', system: 'CATU DAYA SINYAL', subsystem: 'CATU DAYA SINYAL', lokasi: 'Divre IV Tanjungkarang', jumlah_unit: 84, tahun_pemasangan: '2010-01-01', status: 'Aktif' }),
];

// ═══════════════════════════════════════════════
// RISK MATRIX (ringkas per subsystem)
// ═══════════════════════════════════════════════
const riskMatrixData = [
  // DAOP1
  new RiskMatrixModel({ id: 1, unit_kerja_id: 'DAOP1', aset_id: 1, likelihood: 3, consequences: 4 }),   // Interlocking Elektrik - Extreme
  new RiskMatrixModel({ id: 2, unit_kerja_id: 'DAOP1', aset_id: 6, likelihood: 3, consequences: 3 }),   // Track Circuit - High
  new RiskMatrixModel({ id: 3, unit_kerja_id: 'DAOP1', aset_id: 5, likelihood: 2, consequences: 3 }),   // Penggerak Wesel Elektrik - Medium
  new RiskMatrixModel({ id: 4, unit_kerja_id: 'DAOP1', aset_id: 2, likelihood: 2, consequences: 2 }),   // Peraga Sinyal Utama - Medium
  new RiskMatrixModel({ id: 5, unit_kerja_id: 'DAOP1', aset_id: 7, likelihood: 1, consequences: 2 }),   // Axle Counter - Low
  // DIVRE4
  new RiskMatrixModel({ id: 6, unit_kerja_id: 'DIVRE4', aset_id: 27, likelihood: 4, consequences: 4 }), // Interlocking Mekanik - Extreme
  new RiskMatrixModel({ id: 7, unit_kerja_id: 'DIVRE4', aset_id: 35, likelihood: 4, consequences: 3 }), // Kontak Deteksi - Extreme
  new RiskMatrixModel({ id: 8, unit_kerja_id: 'DIVRE4', aset_id: 23, likelihood: 3, consequences: 3 }), // Penggerak Wesel Elektrik - High
  new RiskMatrixModel({ id: 9, unit_kerja_id: 'DIVRE4', aset_id: 19, likelihood: 2, consequences: 2 }), // Peraga Sinyal Utama - Medium
  new RiskMatrixModel({ id: 10, unit_kerja_id: 'DIVRE4', aset_id: 36, likelihood: 1, consequences: 3 }), // Catu Daya - Low
];

// ═══════════════════════════════════════════════
// RISK REGISTER / LxC (per peristiwa risiko)
// ═══════════════════════════════════════════════
const riskRegisterData = [
  new RiskRegisterModel({ id: 1, unit_kerja_id: 'DAOP1', aset_id: 1, part_number: 'SIL-01', peristiwa_risiko: 'Interlocking Failure', penyebab: 'PLC Failure, usia komponen > 5 tahun', rekomendasi: 'Penggantian modul PLC', status: 'Open' }),
  new RiskRegisterModel({ id: 2, unit_kerja_id: 'DAOP1', aset_id: 2, part_number: 'PSE-01', peristiwa_risiko: 'Sinyal Padam', penyebab: 'Kabel putus akibat korosi', rekomendasi: 'Perbaikan kabel instalasi', status: 'In Progress' }),
  new RiskRegisterModel({ id: 3, unit_kerja_id: 'DAOP1', aset_id: 5, part_number: 'PWE-01', peristiwa_risiko: 'Wesel Tidak Mengunci', penyebab: 'Clamp lock terganjal batu kerikil', rekomendasi: 'Pembersihan area wesel rutin', status: 'Closed' }),
  new RiskRegisterModel({ id: 4, unit_kerja_id: 'DAOP1', aset_id: 6, part_number: 'TCR-01', peristiwa_risiko: 'Track Circuit Failure', penyebab: 'Isolasi rel rusak, arus bocor', rekomendasi: 'Ganti isolasi sambungan rel', status: 'Open' }),
  new RiskRegisterModel({ id: 5, unit_kerja_id: 'DAOP1', aset_id: 18, part_number: 'CDS-01', peristiwa_risiko: 'Power Supply Failure', penyebab: 'Baterai UPS drop, usia > 3 tahun', rekomendasi: 'Penggantian baterai UPS', status: 'In Progress' }),
  new RiskRegisterModel({ id: 6, unit_kerja_id: 'DIVRE4', aset_id: 27, part_number: 'SIM-01', peristiwa_risiko: 'Interlocking Mekanik Macet', penyebab: 'Pelumasan kurang, komponen berkarat', rekomendasi: 'Overhaul mekanisme interlocking', status: 'Open' }),
  new RiskRegisterModel({ id: 7, unit_kerja_id: 'DIVRE4', aset_id: 35, part_number: 'KTD-01', peristiwa_risiko: 'Kontak Deteksi Tidak Respon', penyebab: 'Kontak kotor/aus', rekomendasi: 'Pembersihan dan penggantian pedal kontak', status: 'In Progress' }),
  new RiskRegisterModel({ id: 8, unit_kerja_id: 'DIVRE4', aset_id: 23, part_number: 'PWE-02', peristiwa_risiko: 'Wesel Tidak Mengunci', penyebab: 'Penggerak wesel elektrik aus', rekomendasi: 'Penggantian motor penggerak', status: 'Open' }),
];

// ═══════════════════════════════════════════════
// RELIABILITY DATA (ringkasan per subsystem)
// ═══════════════════════════════════════════════
const reliabilityData = [
  new ReliabilityModel({ id: 1, unit_kerja_id: 'DAOP1', aset_id: 1, periode: '2026-07', jumlah_unit: 2, total_operating_hour: 1488, total_downtime: 12, total_uptime: 1476, jumlah_failure: 2, mtbf: 738, mttr: 6, failure_rate: 0.0014, reliability: 0.9986, availability: 0.9919 }),
  new ReliabilityModel({ id: 2, unit_kerja_id: 'DAOP1', aset_id: 6, periode: '2026-07', jumlah_unit: 81, total_operating_hour: 60264, total_downtime: 8, total_uptime: 60256, jumlah_failure: 1, mtbf: 60256, mttr: 8, failure_rate: 0.00002, reliability: 0.99998, availability: 0.9999 }),
  new ReliabilityModel({ id: 3, unit_kerja_id: 'DAOP1', aset_id: 7, periode: '2026-07', jumlah_unit: 119, total_operating_hour: 88536, total_downtime: 4, total_uptime: 88532, jumlah_failure: 1, mtbf: 88532, mttr: 4, failure_rate: 0.00001, reliability: 0.99999, availability: 0.99995 }),
  new ReliabilityModel({ id: 4, unit_kerja_id: 'DIVRE4', aset_id: 19, periode: '2026-07', jumlah_unit: 1, total_operating_hour: 744, total_downtime: 3, total_uptime: 741, jumlah_failure: 1, mtbf: 741, mttr: 3, failure_rate: 0.0013, reliability: 0.9987, availability: 0.9960 }),
  new ReliabilityModel({ id: 5, unit_kerja_id: 'DIVRE4', aset_id: 27, periode: '2026-07', jumlah_unit: 19, total_operating_hour: 14136, total_downtime: 48, total_uptime: 14088, jumlah_failure: 3, mtbf: 4696, mttr: 16, failure_rate: 0.0002, reliability: 0.9998, availability: 0.9966 }),
];

// ═══════════════════════════════════════════════
// LOG KEJADIAN KEGAGALAN (child table)
// ═══════════════════════════════════════════════
const failureLogData = [
  new FailureLogModel({ id: 1, reliability_id: 1, lokasi: 'Stasiun Manggarai', resor: 'Resor 1.1 Manggarai', failure_event: 'Modul CPU PLC Error', penyebab: 'Overheat pada ruang sintel', tindakan: 'Reset PLC dan perbaikan ventilasi', penggantian_sparepart: 'N', tindak_vandalisme: 'N', tanggal_kejadian: '2026-07-05', mulai: '08:15', tanggal_penanganan: '2026-07-05', selesai: '14:15', downtime_jam: 6 }),
  new FailureLogModel({ id: 2, reliability_id: 1, lokasi: 'Stasiun Manggarai', resor: 'Resor 1.1 Manggarai', failure_event: 'Gangguan I/O Module', penyebab: 'Konektor longgar', tindakan: 'Pemasangan ulang konektor', penggantian_sparepart: 'N', tindak_vandalisme: 'N', tanggal_kejadian: '2026-07-18', mulai: '22:30', tanggal_penanganan: '2026-07-19', selesai: '04:30', downtime_jam: 6 }),
  new FailureLogModel({ id: 3, reliability_id: 2, lokasi: 'Stasiun Gambir', resor: 'Resor 1.2 Gambir', failure_event: 'Track Circuit Intermittent', penyebab: 'Isolasi rel retak', tindakan: 'Penggantian isolasi', penggantian_sparepart: 'Y', tindak_vandalisme: 'N', tanggal_kejadian: '2026-07-12', mulai: '03:00', tanggal_penanganan: '2026-07-12', selesai: '11:00', downtime_jam: 8 }),
  new FailureLogModel({ id: 4, reliability_id: 3, lokasi: 'Stasiun Jatinegara', resor: 'Resor 1.3 Jatinegara', failure_event: 'Axle Counter Reset', penyebab: 'Petir menyambar jalur kabel', tindakan: 'Reset sistem dan penggantian surge protector', penggantian_sparepart: 'Y', tindak_vandalisme: 'N', tanggal_kejadian: '2026-07-22', mulai: '15:00', tanggal_penanganan: '2026-07-22', selesai: '19:00', downtime_jam: 4 }),
  new FailureLogModel({ id: 5, reliability_id: 4, lokasi: 'Stasiun Tanjungkarang', resor: 'Resor 4.1 Tanjungkarang', failure_event: 'Relay Kontak Kotor', penyebab: 'Debu dan kelembaban tinggi', tindakan: 'Pembersihan dan pelumasan relay', penggantian_sparepart: 'N', tindak_vandalisme: 'N', tanggal_kejadian: '2026-07-15', mulai: '07:00', tanggal_penanganan: '2026-07-15', selesai: '10:00', downtime_jam: 3 }),
  new FailureLogModel({ id: 6, reliability_id: 5, lokasi: 'Stasiun Tanjungkarang', resor: 'Resor 4.1 Tanjungkarang', failure_event: 'Handle Interlocking Macet', penyebab: 'Pelumasan kurang, karat', tindakan: 'Overhaul dan pelumasan ulang', penggantian_sparepart: 'N', tindak_vandalisme: 'N', tanggal_kejadian: '2026-07-02', mulai: '06:00', tanggal_penanganan: '2026-07-02', selesai: '22:00', downtime_jam: 16 }),
  new FailureLogModel({ id: 7, reliability_id: 5, lokasi: 'Stasiun Kedaton', resor: 'Resor 4.2 Kedaton', failure_event: 'Handle Macet', penyebab: 'Komponen berkarat, usia > 30 tahun', tindakan: 'Penggantian komponen mekanik', penggantian_sparepart: 'Y', tindak_vandalisme: 'N', tanggal_kejadian: '2026-07-10', mulai: '09:00', tanggal_penanganan: '2026-07-10', selesai: '23:00', downtime_jam: 14 }),
  new FailureLogModel({ id: 8, reliability_id: 5, lokasi: 'Stasiun Rejosari', resor: 'Resor 4.3 Rejosari', failure_event: 'Kawat Interlocking Putus', penyebab: 'Tindak vandalisme — pencurian kawat', tindakan: 'Pemasangan kawat baru dan pelaporan', penggantian_sparepart: 'Y', tindak_vandalisme: 'Y', tanggal_kejadian: '2026-07-20', mulai: '04:00', tanggal_penanganan: '2026-07-20', selesai: '22:00', downtime_jam: 18 }),
];

// ═══════════════════════════════════════════════
// REPOSITORY CLASS
// ═══════════════════════════════════════════════
export class DummyRamsRepository {
  async getUnitKerjaList() {
    await delay(300);
    return unitKerjaData;
  }

  async getAssets(unitKerjaKode) {
    await delay(300);
    if (unitKerjaKode) {
      return assetData.filter(item => item.unit_kerja_id === unitKerjaKode);
    }
    return assetData;
  }

  async getRiskMatrix(unitKerjaKode) {
    await delay(300);
    if (unitKerjaKode) {
      return riskMatrixData.filter(item => item.unit_kerja_id === unitKerjaKode);
    }
    return riskMatrixData;
  }

  async getRiskRegisters(unitKerjaKode) {
    await delay(300);
    if (unitKerjaKode) {
      return riskRegisterData.filter(item => item.unit_kerja_id === unitKerjaKode);
    }
    return riskRegisterData;
  }

  async getReliabilityData(unitKerjaKode) {
    await delay(300);
    if (unitKerjaKode) {
      return reliabilityData.filter(item => item.unit_kerja_id === unitKerjaKode);
    }
    return reliabilityData;
  }

  async getFailureLogs(reliabilityId) {
    await delay(300);
    if (reliabilityId) {
      return failureLogData.filter(item => item.reliability_id === reliabilityId);
    }
    return failureLogData;
  }

  async getDashboardSummary(unitKerjaKode) {
    await delay(300);

    const assets = unitKerjaKode 
      ? assetData.filter(item => item.unit_kerja_id === unitKerjaKode) 
      : assetData;
      
    const risks = unitKerjaKode 
      ? riskMatrixData.filter(item => item.unit_kerja_id === unitKerjaKode)
      : riskMatrixData;

    const reliabilities = unitKerjaKode 
      ? reliabilityData.filter(item => item.unit_kerja_id === unitKerjaKode)
      : reliabilityData;

    const registers = unitKerjaKode 
      ? riskRegisterData.filter(item => item.unit_kerja_id === unitKerjaKode)
      : riskRegisterData;

    let risikoExtreme = 0;
    let risikoHigh = 0;
    
    risks.forEach(risk => {
      if (risk.rating >= 12) risikoExtreme++;
      else if (risk.rating >= 8 && risk.rating <= 11) risikoHigh++;
    });

    const totalAvailability = reliabilities.reduce((sum, item) => sum + item.availability, 0);
    const avgAvailability = reliabilities.length > 0 ? totalAvailability / reliabilities.length : 0;

    const totalUnit = assets.reduce((sum, item) => sum + (item.jumlah_unit || 0), 0);

    let totalFailure = 0;
    reliabilities.forEach(rel => {
      const logs = failureLogData.filter(log => log.reliability_id === rel.id);
      totalFailure += logs.length;
    });

    const openRegisters = registers.filter(r => r.status === 'Open').length;

    return {
      totalAset: assets.length,
      totalUnit,
      risikoExtreme,
      risikoHigh,
      avgAvailability,
      totalFailure,
      totalProposalReorder: openRegisters
    };
  }
}
