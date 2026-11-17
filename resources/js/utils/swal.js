import Swal from 'sweetalert2';

const PuritronicToast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    showCloseButton: true,
    timer: 3200,
    timerProgressBar: true,
    background: '#FFFFFF',
    color: '#211B35',
    customClass: {
        popup: 'puritronic-swal-toast',
        title: 'puritronic-swal-toast-title',
        timerProgressBar: 'puritronic-swal-progress',
    },
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

export const toastSuccess = (message) => {
    if (!message) return;
    return PuritronicToast.fire({
        icon: 'success',
        iconColor: '#26845B',
        title: message,
    });
};

export const toastError = (message) => {
    if (!message) return;
    return PuritronicToast.fire({
        icon: 'error',
        iconColor: '#E82147',
        title: message,
        timer: 4500,
    });
};

export const toastWarning = (message) => {
    if (!message) return;
    return PuritronicToast.fire({
        icon: 'warning',
        iconColor: '#D99A27',
        title: message,
        timer: 4200,
    });
};

export const toastInfo = (message) => {
    if (!message) return;
    return PuritronicToast.fire({
        icon: 'info',
        iconColor: '#0071BA',
        title: message,
    });
};

/**
 * Dispara los toasts correspondientes a los mensajes flash compartidos por Laravel/Inertia.
 */
export const showFlashNotifications = (flash) => {
    if (!flash || typeof flash !== 'object') return;

    if (flash.success) {
        toastSuccess(flash.success);
    }
    if (flash.warning) {
        toastWarning(flash.warning);
    }
    if (flash.error) {
        toastError(flash.error);
    }
    if (flash.info) {
        toastInfo(flash.info);
    }
};

/**
 * Diálogo modal de confirmación con identidad Puritronic (reemplaza confirm() nativo y modales manuales).
 */
export const confirmAction = async ({
    title = '¿Confirmar acción?',
    text = '',
    confirmText = 'Sí, continuar',
    cancelText = 'Cancelar',
    icon = 'question',
    danger = false,
} = {}) => {
    const result = await Swal.fire({
        title,
        text,
        icon,
        iconColor: danger ? '#E82147' : '#0071BA',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        reverseButtons: true,
        focusCancel: danger,
        background: '#FFFFFF',
        color: '#211B35',
        buttonsStyling: false,
        customClass: {
            popup: 'puritronic-swal-modal',
            title: 'puritronic-swal-modal-title',
            htmlContainer: 'puritronic-swal-modal-text',
            actions: 'puritronic-swal-actions',
            confirmButton: danger
                ? 'puritronic-swal-btn puritronic-swal-btn-danger'
                : 'puritronic-swal-btn puritronic-swal-btn-primary',
            cancelButton: 'puritronic-swal-btn puritronic-swal-btn-cancel',
        },
    });

    return Boolean(result.isConfirmed);
};

/**
 * Alerta modal informativa o de validación (reemplaza alert() nativo).
 */
export const alertMessage = ({
    title = 'Atención',
    text = '',
    icon = 'warning',
    confirmText = 'Entendido',
} = {}) => {
    const iconColorMap = {
        warning: '#D99A27',
        error: '#E82147',
        success: '#26845B',
        info: '#0071BA',
    };

    return Swal.fire({
        title,
        text,
        icon,
        iconColor: iconColorMap[icon] || '#0071BA',
        confirmButtonText: confirmText,
        background: '#FFFFFF',
        color: '#211B35',
        buttonsStyling: false,
        customClass: {
            popup: 'puritronic-swal-modal',
            title: 'puritronic-swal-modal-title',
            htmlContainer: 'puritronic-swal-modal-text',
            actions: 'puritronic-swal-actions',
            confirmButton: 'puritronic-swal-btn puritronic-swal-btn-primary',
        },
    });
};

export const alertWarning = (text, title = 'Atención') =>
    alertMessage({ title, text, icon: 'warning' });

export const alertError = (text, title = 'Error') =>
    alertMessage({ title, text, icon: 'error' });

export default Swal;
