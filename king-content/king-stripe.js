/*



	File: king-content/king-admin.js
	Version: See define()s at top of king-include/king-base.php
	Description: Javascript for admin pages to handle Ajax-triggered operations


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: LICENCE.html
*/

var stripeKey = document.querySelector('#payment-box').dataset.consumerKey;
var createOrderUrl = document.querySelector('#payment-box').dataset.createOrderUrl;
var returnUrl = document.querySelector('#payment-box').dataset.returnUrl;
var stripe = Stripe(stripeKey);


let elements; // Define card elements
const paymentFrm = document.querySelector("#payment-form"); // Select payment form element

// Get payment_intent_client_secret param from URL
const clientSecretParam = new URLSearchParams(window.location.search).get(
    "payment_intent_client_secret"
);

// Check whether the payment_intent_client_secret is already exist in the URL
setProcessing(true);

function memClick(myRadio) {
    var st = document.getElementById('mem_plan');
    var pp = document.getElementById('memp_plan');
    if (st) {
        st.value = myRadio.value;
    }
    if (pp) {
        pp.value = myRadio.value;
    }
  
  let button = document.getElementById("memnext");
  button.disabled = false;
    if(!clientSecretParam){
        initialize();
    }
}

function cmemnext() {
  var ast = document.getElementById('mem_plan');
  var app = document.getElementById('memp_plan');
  var cb = document.getElementById('credit-box');

    if (ast) {
      ast.value = cb.value;
    }
    if (app) {
      app.value = cb.value;
    }


  var element = document.getElementById("membership");
  element.classList.toggle("step-2");
  if(!clientSecretParam){
    initialize();
  }
}

// Check the PaymentIntent creation status
checkStatus();

// Attach an event handler to payment form
paymentFrm.addEventListener("submit", handleSubmit);

// Fetches a payment intent and captures the client secret
let payment_intent_id;
async function initialize() {
      let usd = document.getElementById("mem_plan").value;
    const { id, clientSecret } = await fetch(createOrderUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ request_type:'create_payment_intent', price: usd, }),
    }).then((r) => r.json());
  
    const appearance = {
        theme: 'stripe',
        rules: {
            '.Label': {
                fontWeight: 'bold',
                textTransform: 'uppercase',
            }
        }
    };
  
    elements = stripe.elements({ clientSecret, appearance });
  

    const paymentElement = elements.create("payment");
    paymentElement.mount("#payment-element");

    payment_intent_id = id;
    setProcessing(false);
}



async function handleSubmit(e) {
    e.preventDefault();
    setLoading(true);

    let emailz = document.getElementById("email").value;
    let namez = document.getElementById("customer_name").value;

    const { id, customer_id } = await fetch(createOrderUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ request_type:'create_customer', payment_intent_id: payment_intent_id, name: namez, email: emailz, }),
    }).then((r) => r.json());
  
    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
            // Make sure to change this to your payment completion page
            return_url: returnUrl+'&customer_id='+customer_id,
        },
  });
  
    // This point will only be reached if there is an immediate error when
    // confirming the payment. Otherwise, your customer will be redirected to
    // your `return_url`. For some payment methods like iDEAL, your customer will
    // be redirected to an intermediate site first to authorize the payment, then
    // redirected to the `return_url`.
    if (error.type === "card_error" || error.type === "validation_error") {
        showMessage(error.message);
    } else {
        showMessage("An unexpected error occured.");
    }
  
    setLoading(false);
}
// Fetches the payment intent status after payment submission
async function checkStatus() {
  const clientSecret = new URLSearchParams(window.location.search).get(
    "payment_intent_client_secret"
  );

    const customerID = new URLSearchParams(window.location.search).get(
        "customer_id"
    );

    if (!clientSecret) {
        return;
    }
  const { paymentIntent } = await stripe.retrievePaymentIntent(clientSecret);
  
  if (paymentIntent) {
  switch (paymentIntent.status) {
    case "succeeded":
      fetch(createOrderUrl, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ request_type:'payment_insert', payment_intent: paymentIntent, customer_id: customerID, }),
                }).then((r) => r.json());
                break;
      showMessage("Payment succeeded!");
      break;
    case "processing":
      showMessage("Your payment is processing.");
      break;
    case "requires_payment_method":
      showMessage("Your payment was not successful, please try again.");
      break;
    default:
      showMessage("Something went wrong.");
      break;
  }
  } else {
        showMessage("Something went wrong.");

    }
}



// ------- UI helpers -------

function showMessage(messageText) {
  const messageContainer = document.querySelector("#payment-message");

  messageContainer.classList.remove("hidden");
  messageContainer.textContent = messageText;

  setTimeout(function () {
    messageContainer.classList.add("hidden");
    messageText.textContent = "";
  }, 4000);
}

// Show a spinner on payment submission
function setLoading(isLoading) {
  if (isLoading) {
    // Disable the button and show a spinner
    document.querySelector("#submit").disabled = true;
    document.querySelector("#spinner").classList.remove("hide");

  } else {
    document.querySelector("#submit").disabled = false;
    document.querySelector("#spinner").classList.add("hide");

  }
}

// Show a spinner on payment form processing
function setProcessing(isProcessing) {
    if (isProcessing) {

        document.querySelector("#spinner").classList.remove("hide");
    } else {

        document.querySelector("#spinner").classList.add("hide");
    }
}