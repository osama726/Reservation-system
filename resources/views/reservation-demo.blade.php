<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reservation Demo</title>
        @vite(['resources/css/reservation-demo.css', 'resources/js/reservation-demo.js'])
    </head>
    <body>
        <div class="demo-banner">
            <span class="demo-badge">Demo Front</span>
            <p>
                Simple Blade UI for testing the reservation flow and API in a fast, easy way.
                This is not a production dashboard; it is only a lightweight interface for validation and demonstration.
            </p>
        </div>

        <div class="container">
            <h1>Reservation Service Demo</h1>
            <p class="subtitle">Quick Blade front for testing create, availability, update, history, and admin capacity routes.</p>

            <div class="grid">
                <section class="card">
                    <h2>Create Reservation</h2>
                    <form id="createForm">
                        <label>Resource ID</label>
                        <input type="number" id="resource_id" value="1" required>

                        <label>Units</label>
                        <input type="number" id="units" value="2" min="1" required>

                        <label>Start time</label>
                        <input type="datetime-local" id="start_time" value="2030-01-01T10:00" required>

                        <label>End time</label>
                        <input type="datetime-local" id="end_time" value="2030-01-01T11:00" required>

                        <button type="submit">Create Reservation</button>
                    </form>
                </section>

                <section class="card">
                    <h2>Check Availability</h2>
                    <form id="availabilityForm">
                        <label>Resource ID</label>
                        <input type="number" id="availability_resource_id" value="1" required>

                        <label>Start time</label>
                        <input type="datetime-local" id="availability_start" value="2030-01-01T10:00" required>

                        <label>End time</label>
                        <input type="datetime-local" id="availability_end" value="2030-01-01T11:00" required>

                        <button type="submit" class="secondary">Check Availability</button>
                    </form>
                </section>

                <section class="card">
                    <h2>Reservation History</h2>
                    <form id="historyForm">
                        <label>Reservation ID</label>
                        <input type="text" id="reservation_id" placeholder="paste reservation id" required>

                        <button type="submit" class="secondary">Load History</button>
                    </form>
                </section>

                <section class="card">
                    <h2>Quick actions</h2>
                    <form id="confirmForm">
                        <label>Reservation ID</label>
                        <input type="text" id="confirm_id" placeholder="reservation id" required>
                        <button type="submit" class="secondary">Confirm</button>
                    </form>

                    <form id="cancelForm" style="margin-top: 16px;">
                        <label>Reservation ID</label>
                        <input type="text" id="cancel_id" placeholder="reservation id" required>
                        <button type="submit" class="danger">Cancel</button>
                    </form>
                </section>

                <section class="card">
                    <h2>Update Reservation</h2>
                    <form id="updateForm">
                        <label>Reservation ID</label>
                        <input type="text" id="update_id" placeholder="reservation id" required>

                        <label>New Units (optional)</label>
                        <input type="number" id="update_units" min="1" placeholder="leave blank to keep current">

                        <label>New Start Time (optional)</label>
                        <input type="datetime-local" id="update_start_time">

                        <label>New End Time (optional)</label>
                        <input type="datetime-local" id="update_end_time">

                        <button type="submit" class="secondary">Update Reservation</button>
                    </form>
                </section>

                <section class="card">
                    <h2>Admin: Update Capacity</h2>
                    <form id="adminCapacityForm">
                        <label>Resource ID</label>
                        <input type="number" id="admin_resource_id" value="1" required>

                        <label>New Capacity</label>
                        <input type="number" id="admin_capacity" value="5" min="0" required>

                        <label>Admin Token</label>
                        <input type="text" id="admin_token" value="admin-token-123" required>

                        <button type="submit" class="secondary">Update Capacity</button>
                    </form>
                </section>
            </div>

            <div id="output" class="output">Result will appear here...</div>
        </div>

    </body>
</html>
