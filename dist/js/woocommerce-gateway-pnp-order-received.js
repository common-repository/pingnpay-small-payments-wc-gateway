(()=>{var e,a;e=jQuery,a={pending:{message:"You are almost there - open your wallet to pay"},processing:{message:"Your order has been paid and is now being processed by the retailer."},completed:{message:"Your order has been paid and fulfilled by the retailer."},failed:{message:"failed"},cancelled:{message:"cancelled"},"on-hold":{message:"on hold"}},e.fn.checkOrderStatus=function(){e.ajax({type:"post",dataType:"json",url:wc_gateway_pnp_order_received_params.ajaxurl,data:{action:"pnp_wc_gateway_check_order_status",ajaxnonce:wc_gateway_pnp_order_received_params.ajaxnonce,wc_order_id:wc_gateway_pnp_order_received_params.wc_order_id},success:function(r){var d="".concat(a[r.order_status].message);r.error&&(d+="<br />(Error: ".concat(r.error,")")),e(".wc-gateway-pnp-lds-message").html(d);var t=1e3;"processing"===r.order_status||"completed"===r.order_status?(e(".wc-gateway-pnp-lds-ring").css("visibility","hidden"),setTimeout((function(){r.redirect?window.location.href=r.redirect:location.reload()}),t)):"failed"===r.order_status||"cancelled"===r.order_status?(e(".wc-gateway-pnp-lds-ring").css("visibility","hidden"),setTimeout((function(){location.reload()}),t)):setTimeout((function(){e().checkOrderStatus()}),t)},error:function(e){console.error("checkOrderStatus Error:",e)}})},e.fn.initModal=function(){e('<div class="wc-gateway-pnp-lds-container">\n      <div>\n        <div class="wc-gateway-pnp-lds-message"></div>\n        <div class="wc-gateway-pnp-lds-ring"><div></div><div></div><div></div><div></div></div>\n      </div>\n    </div>').appendTo("body")},e(document).ready((function(){"direct"==wc_gateway_pnp_order_received_params.pnp_payment_type&&document.body.setAttribute("data-pnp-direct-payment-payload",wc_gateway_pnp_order_received_params.pnp_direct_payment_payload),"pending"===wc_gateway_pnp_order_received_params.wc_order_status&&(e().initModal(),e().checkOrderStatus())}))})();