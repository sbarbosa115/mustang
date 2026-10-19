import {useTranslation} from 'react-i18next';
import {useNavigate} from 'react-router';
import {AddVehicleDialog} from '@/features/add-vehicle';
import {UploadVehiclesDialog} from '@/features/upload-vehicles-csv';
import {InventoryGrid} from '@/widgets/inventory-grid';
import {PageHeader} from '@/shared/ui/page';

/** Search, filter and sort the dealership's vehicles; add one or upload a CSV (FR-21). */
export function InventoryPage() {
  const {t} = useTranslation('inventory');
  const navigate = useNavigate();
  return (
    <>
      <PageHeader
        title={t('title')}
        description={t('description')}
        actions={
          <>
            <UploadVehiclesDialog />
            <AddVehicleDialog onAdded={(id) => navigate(`/inventory/${id}`)} />
          </>
        }
      />
      <InventoryGrid />
    </>
  );
}
