import { IAssetRepository } from '@/domain/repositories/i-asset.repository';
import { AssetModel } from '@/domain/models/asset.model';
import mockAssets from '@/infrastructure/dummy-data/assets.json';

export class MockAssetRepository extends IAssetRepository {
  constructor() {
    super();
    // Load mock data into in-memory array
    this.assets = mockAssets.map(item => new AssetModel(item));
  }

  async getAll(userScope) {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 150));

    // Enforce Regional Data Isolation
    if (userScope.isPusat()) {
      return [...this.assets];
    }
    
    // Akun Daop/Divre only sees its own unit_kerja_id
    return this.assets.filter(asset => asset.unit_kerja_id === userScope.unit_kerja_id);
  }

  async getById(id, userScope) {
    await new Promise(resolve => setTimeout(resolve, 100));
    const asset = this.assets.find(a => a.id === Number(id));

    if (!asset) return null;

    // Check regional boundary authorization
    if (!userScope.canAccessUnitKerja(asset.unit_kerja_id)) {
      throw new Error('403 Forbidden: Regional access violation');
    }

    return asset;
  }

  async create(assetData, userScope) {
    await new Promise(resolve => setTimeout(resolve, 200));

    // Derive unit_kerja_id if Daop/Divre
    const targetUnitKerja = userScope.isPusat() 
      ? assetData.unit_kerja_id 
      : userScope.unit_kerja_id;

    const newAsset = new AssetModel({
      ...assetData,
      id: Date.now(),
      unit_kerja_id: targetUnitKerja,
      is_active: true
    });

    this.assets.push(newAsset);
    return newAsset;
  }

  async update(id, assetData, userScope) {
    const index = this.assets.findIndex(a => a.id === Number(id));
    if (index === -1) throw new Error('Asset not found');

    const existing = this.assets[index];

    // Authorization check
    if (!userScope.canAccessUnitKerja(existing.unit_kerja_id)) {
      throw new Error('403 Forbidden: Regional access violation');
    }

    const updated = new AssetModel({
      ...existing,
      ...assetData,
      id: existing.id, // Immutable ID
      unit_kerja_id: existing.unit_kerja_id // Derived from asset identity
    });

    this.assets[index] = updated;
    return updated;
  }

  async deactivate(id, userScope) {
    const asset = await this.getById(id, userScope);
    if (asset) {
      asset.is_active = false;
    }
    return asset;
  }
}
