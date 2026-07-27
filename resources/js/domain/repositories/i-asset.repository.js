/**
 * Asset Repository Interface (Contract)
 * Clean Architecture contract that both MockAssetRepository and HttpAssetRepository must implement.
 */
export class IAssetRepository {
  async getAll(userScope) {
    throw new Error('Method getAll() must be implemented');
  }

  async getById(id, userScope) {
    throw new Error('Method getById() must be implemented');
  }

  async create(assetData, userScope) {
    throw new Error('Method create() must be implemented');
  }

  async update(id, assetData, userScope) {
    throw new Error('Method update() must be implemented');
  }

  async deactivate(id, userScope) {
    throw new Error('Method deactivate() must be implemented');
  }
}
