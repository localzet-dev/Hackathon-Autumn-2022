"use strict";

// Class definition
var KTSigninGeneral = function () {
    // Elements
    var form;
    var submitButton;
    var validator;

    // Handle form
    var handleForm = function (e) {
        // Init form validation rules. For more info check the FormValidation plugin's official documentation:https://formvalidation.io/
        validator = FormValidation.formValidation(
            form,
            {
                fields: {
                    'email': {
                        validators: {
                            notEmpty: {
                                message: 'E-mail обязателен'
                            },
                            emailAddress: {
                                message: 'Это не является адресом электронной почты'
                            }
                        }
                    },
                    'password': {
                        validators: {
                            notEmpty: {
                                message: 'Пароль обязателен'
                            }
                        }
                    }
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: '.fv-row'
                    })
                }
            }
        );

        // Handle form submit
        submitButton.addEventListener('click', function (e) {
            // Prevent button default action
            e.preventDefault();

            // Validate form
            validator.validate().then(function (status) {
                if (status == 'Valid') {
                    // Show loading indication
                    submitButton.setAttribute('data-kt-indicator', 'on');

                    // Disable button to avoid multiple click 
                    submitButton.disabled = true;

                    $.ajax({
                        url: '/auth',
                        type: 'POST',
                        dataType: "json",
                        data: {
                            email: form.querySelector('[name="email"]').value,
                            password: form.querySelector('[name="password"]').value,
                        },
                        success: function (data, textStatus) {
                            // console.log(data);

                            // Hide loading indication
                            submitButton.removeAttribute('data-kt-indicator');

                            // Enable button
                            submitButton.disabled = false;

                            // Show message popup. For more info check the plugin's official documentation: https://sweetalert2.github.io/
                            if (data.status == 200) {
                                var redirectUrl = form.getAttribute('data-rx-cert-url');
                                if (redirectUrl) {
                                    location.href = redirectUrl + '?user=' + data.data.user + '&access_token=' + data.data.access_token;
                                }
                            } else {

                                Swal.fire({
                                    text: data.data ?? data.error,
                                    icon: 'error',
                                    buttonsStyling: false,
                                    confirmButtonText: "Закрыть",
                                    customClass: {
                                        confirmButton: "btn btn-primary"
                                    }
                                }).then(function (result) {
                                    if (result.isConfirmed && data.status == 200) {
                                        form.querySelector('[name="email"]').value = "";
                                        form.querySelector('[name="password"]').value = "";

                                        //form.submit(); // submit form

                                    }
                                });
                            }
                        },
                        error: function (jqXHR, textStatus, errorThrown) {
                            submitButton.removeAttribute('data-kt-indicator');
                            // console.log(textStatus, errorThrown);
                            Swal.fire({
                                text: errorThrown,
                                icon: 'error',
                                buttonsStyling: false,
                                confirmButtonText: "Закрыть",
                                customClass: {
                                    confirmButton: "btn btn-primary"
                                }
                            });
                        }
                    });
                } else {
                    // Show error popup. For more info check the plugin's official documentation: https://sweetalert2.github.io/
                    submitButton.removeAttribute('data-kt-indicator');
                    Swal.fire({
                        text: "Ошибка! Проверьте ваши данные и попробуйте ещё раз.",
                        icon: "error",
                        buttonsStyling: false,
                        confirmButtonText: "Хорошо!",
                        customClass: {
                            confirmButton: "btn btn-primary"
                        }
                    });
                }
            });
        });
    }

    // Public functions
    return {
        // Initialization
        init: function () {
            form = document.querySelector('#kt_sign_in_form');
            submitButton = document.querySelector('#kt_sign_in_submit');

            handleForm();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTSigninGeneral.init();
});
