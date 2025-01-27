<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adyen Checkout</title>
    <link rel="stylesheet" href="https://checkoutshopper-test.adyen.com/checkoutshopper/sdk/5.40.0/adyen.css" />
</head>

<body>
    <h1>Adyen Checkout</h1>
    <div id="dropin-container"></div>

    <script src="https://checkoutshopper-test.adyen.com/checkoutshopper/sdk/5.40.0/adyen.js"></script>
    <script>
        (async () => {
            try {
                const paymentMethodsResponse = await fetch('/get-payment-methods', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                }).then(response => response.json());

                const checkout = await AdyenCheckout({
                    environment: "{{ env('ADYEN_ENVIRONMENT') }}",
                    clientKey: "{{ env('ADYEN_CLIENT_KEY') }}",
                    paymentMethodsResponse,
                    onSubmit: async (state, dropin) => {
                        const response = await fetch('/process-payment', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify(state.data),
                        }).then(res => res.json());

                        if (response.action) {
                            dropin.handleAction(response.action); // Handle additional actions
                        } else if (response.resultCode === "Authorised") {
                            window.location.href = response.redirectUrl;
                        } else {
                            window.location.href = response.redirectUrl;
                        }
                    },
                });

                checkout.create('dropin').mount('#dropin-container');
            } catch (error) {
                console.error("Error:", error);
            }
        })();
    </script>
</body>

</html>
