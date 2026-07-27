/**
 * User & Role Domain Model
 * Strictly supports exactly TWO roles:
 * 1. Akun Pusat (unit_kerja_id: null, full access across regions)
 * 2. Akun Daop/Divre (unit_kerja_id: 'DAOP1', isolated scope)
 */
export const ROLES = {
  PUSAT: 'Akun Pusat',
  DAOP_DIVRE: 'Akun Daop/Divre'
};

export class UserModel {
  constructor({ id, username, name, role, unit_kerja_id = null }) {
    this.id = id;
    this.username = username;
    this.name = name;
    this.role = role;
    this.unit_kerja_id = unit_kerja_id; // null if Akun Pusat
  }

  isPusat() {
    return this.role === ROLES.PUSAT;
  }

  isDaopDivre() {
    return this.role === ROLES.DAOP_DIVRE;
  }

  canAccessUnitKerja(unitKerjaId) {
    if (this.isPusat()) return true;
    return this.unit_kerja_id === unitKerjaId;
  }
}
