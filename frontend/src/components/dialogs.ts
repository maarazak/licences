"use client";

// Build sans injection de <style> : la feuille de style est livrée par le site (compatible avec la CSP stricte).
import Swal from "sweetalert2/dist/sweetalert2.esm.js";
import "sweetalert2/dist/sweetalert2.min.css";

const customClass = {
  popup: "swal-popup",
  title: "swal-title",
  htmlContainer: "swal-text",
  actions: "swal-actions",
  confirmButton: "btn btn-primary",
  cancelButton: "btn btn-soft",
};

export type ConfirmOptions = {
  title: string;
  text?: string;
  confirmLabel: string;
  cancelLabel: string;
  /** Action risquée (suspendre, créditer, clôturer…) : bouton de confirmation rouge. */
  danger?: boolean;
};

/** Demande une confirmation. Résout à `true` seulement si l'utilisateur confirme. */
export async function confirmDialog({ title, text, confirmLabel, cancelLabel, danger }: ConfirmOptions): Promise<boolean> {
  const result = await Swal.fire({
    title,
    text,
    icon: danger ? "warning" : "question",
    iconColor: danger ? "#c0271b" : "#0d5c4a",
    showCancelButton: true,
    confirmButtonText: confirmLabel,
    cancelButtonText: cancelLabel,
    reverseButtons: true,
    focusCancel: !!danger,
    buttonsStyling: false,
    heightAuto: false,
    customClass: { ...customClass, confirmButton: danger ? "btn btn-danger-solid" : "btn btn-brand" },
  });
  return result.isConfirmed;
}

/** Boîte « traitement en cours » (cercle animé, non fermable) pour les opérations longues. */
export function showBusy(message: string): void {
  void Swal.fire({
    title: message,
    allowOutsideClick: false,
    allowEscapeKey: false,
    allowEnterKey: false,
    showConfirmButton: false,
    buttonsStyling: false,
    heightAuto: false,
    customClass: { ...customClass, popup: "swal-popup swal-busy" },
    didOpen: () => Swal.showLoading(),
  });
}

/** Ferme la boîte de traitement, sans toucher à une autre boîte déjà affichée (ex. le résultat). */
export function hideBusy(): void {
  if (Swal.getPopup()?.classList.contains("swal-busy")) Swal.close();
}

const toastMixin = Swal.mixin({
  toast: true,
  position: "top-end",
  showConfirmButton: false,
  timer: 5000,
  timerProgressBar: true,
  heightAuto: false,
  customClass: { popup: "swal-toast", title: "swal-toast-title", timerProgressBar: "swal-toast-progress" },
  didOpen: (el) => {
    el.addEventListener("mouseenter", Swal.stopTimer);
    el.addEventListener("mouseleave", Swal.resumeTimer);
  },
});

/** Notification discrète (résultat d'une action). */
export function notify(kind: "success" | "error", message: string): void {
  void toastMixin.fire({ icon: kind, iconColor: kind === "success" ? "#0f7a4f" : "#c0271b", title: message });
}
