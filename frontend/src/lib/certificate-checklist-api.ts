import api, { unwrapData } from './api'
import type { CertificateEmissionChecklist } from '@/types/calibration'

interface ApiDataResponse<T> {
  data: T
}

export const certificateChecklistApi = {
  show: (calibrationId: number) =>
    api
      .get<ApiDataResponse<CertificateEmissionChecklist>>(
        `/certificate-emission-checklist/${calibrationId}`,
      )
      .then((r) => unwrapData<CertificateEmissionChecklist>(r)),

  storeOrUpdate: (data: Partial<CertificateEmissionChecklist> & { equipment_calibration_id: number }) =>
    api
      .post<ApiDataResponse<CertificateEmissionChecklist>>(
        '/certificate-emission-checklist',
        data,
      )
      .then((r) => unwrapData<CertificateEmissionChecklist>(r)),
}
