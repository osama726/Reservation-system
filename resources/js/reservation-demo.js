const output = document.getElementById("output");
const baseUrl = "/api";

function log(data) {
    output.textContent = JSON.stringify(data, null, 2);
}

function makeKey(prefix) {
    return `${prefix}-${crypto.randomUUID()}`;
}

function toUtc(dateString) {
    const date = new Date(dateString);
    return date.toISOString();
}

document
    .getElementById("createForm")
    ?.addEventListener("submit", async function (event) {
        event.preventDefault();

        const payload = {
            resource_id: Number(document.getElementById("resource_id").value),
            units: Number(document.getElementById("units").value),
            start_time: toUtc(document.getElementById("start_time").value),
            end_time: toUtc(document.getElementById("end_time").value),
        };

        try {
            const response = await fetch(`${baseUrl}/reservations`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Idempotency-Key": makeKey("create"),
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();
            log({ status: response.status, data });

            if (response.ok && data.data?.id) {
                document.getElementById("reservation_id").value = data.data.id;
                document.getElementById("confirm_id").value = data.data.id;
                document.getElementById("cancel_id").value = data.data.id;
                document.getElementById("update_id").value = data.data.id;
            }
        } catch (error) {
            log({ error: error.message });
        }
    });

document
    .getElementById("availabilityForm")
    ?.addEventListener("submit", async function (event) {
        event.preventDefault();

        const resourceId = document.getElementById(
            "availability_resource_id",
        ).value;
        const start = toUtc(
            document.getElementById("availability_start").value,
        );
        const end = toUtc(document.getElementById("availability_end").value);

        try {
            const response = await fetch(
                `${baseUrl}/resources/${resourceId}/availability?start_time=${encodeURIComponent(start)}&end_time=${encodeURIComponent(end)}`,
            );
            const data = await response.json();
            log({ status: response.status, data });
        } catch (error) {
            log({ error: error.message });
        }
    });

document
    .getElementById("historyForm")
    ?.addEventListener("submit", async function (event) {
        event.preventDefault();

        const reservationId = document.getElementById("reservation_id").value;

        try {
            const response = await fetch(
                `${baseUrl}/reservations/${reservationId}/history`,
            );
            const data = await response.json();
            log({ status: response.status, data });
        } catch (error) {
            log({ error: error.message });
        }
    });

document
    .getElementById("confirmForm")
    ?.addEventListener("submit", async function (event) {
        event.preventDefault();

        const reservationId = document.getElementById("confirm_id").value;

        try {
            const response = await fetch(
                `${baseUrl}/reservations/${reservationId}/confirm`,
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Idempotency-Key": makeKey("confirm"),
                    },
                    body: JSON.stringify({}),
                },
            );

            const data = await response.json();
            log({ status: response.status, data });
        } catch (error) {
            log({ error: error.message });
        }
    });

document
    .getElementById("cancelForm")
    ?.addEventListener("submit", async function (event) {
        event.preventDefault();

        const reservationId = document.getElementById("cancel_id").value;

        try {
            const response = await fetch(
                `${baseUrl}/reservations/${reservationId}/cancel`,
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Idempotency-Key": makeKey("cancel"),
                    },
                    body: JSON.stringify({}),
                },
            );

            const data = await response.json();
            log({ status: response.status, data });
        } catch (error) {
            log({ error: error.message });
        }
    });

document
    .getElementById("updateForm")
    ?.addEventListener("submit", async function (event) {
        event.preventDefault();

        const reservationId = document.getElementById("update_id").value;
        const payload = {};

        if (document.getElementById("update_units").value !== "") {
            payload.units = Number(
                document.getElementById("update_units").value,
            );
        }

        if (document.getElementById("update_start_time").value !== "") {
            payload.start_time = toUtc(
                document.getElementById("update_start_time").value,
            );
        }

        if (document.getElementById("update_end_time").value !== "") {
            payload.end_time = toUtc(
                document.getElementById("update_end_time").value,
            );
        }

        try {
            const response = await fetch(
                `${baseUrl}/reservations/${reservationId}`,
                {
                    method: "PUT",
                    headers: {
                        "Content-Type": "application/json",
                        "Idempotency-Key": makeKey("update"),
                    },
                    body: JSON.stringify(payload),
                },
            );

            const data = await response.json();
            log({ status: response.status, data });
        } catch (error) {
            log({ error: error.message });
        }
    });

document
    .getElementById("adminCapacityForm")
    ?.addEventListener("submit", async function (event) {
        event.preventDefault();

        const resourceId = document.getElementById("admin_resource_id").value;
        const capacity = Number(
            document.getElementById("admin_capacity").value,
        );
        const token = document.getElementById("admin_token").value;

        try {
            const response = await fetch(
                `${baseUrl}/resources/${resourceId}/capacity`,
                {
                    method: "PUT",
                    headers: {
                        "Content-Type": "application/json",
                        "X-Admin-Secret-Token": token,
                        "Idempotency-Key": makeKey("capacity"),
                    },
                    body: JSON.stringify({ capacity }),
                },
            );

            const data = await response.json();
            log({ status: response.status, data });
        } catch (error) {
            log({ error: error.message });
        }
    });
