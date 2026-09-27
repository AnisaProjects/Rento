-- Starter FAQ content for the accordion on faqs.php (content change, not schema).
-- Each question is an <h3>; the answer is everything until the next <h3>.
-- Edit in admin -> Manage Pages -> FAQs.
--
-- Previous content (test text), kept so it can be restored:
--   UPDATE tblpages SET detail = UNHEX('0909090909090909090909090909090909090909090909090909090909093C7370616E207374796C653D22636F6C6F723A2072676228302C20302C2030293B20666F6E742D66616D696C793A202671756F743B4F70656E2053616E732671756F743B2C20417269616C2C2073616E732D73657269663B20666F6E742D73697A653A20313470783B20746578742D616C69676E3A206A7573746966793B223E416464726573732D2D2D2D2D2D5465737420266E6273703B20266E6273703B64736664736664733C2F7370616E3E') WHERE type = 'faqs';

UPDATE tblpages SET detail = '<h3>How do I book a car?</h3>
<p>Open the car you like, choose your pickup and return dates in the booking box and send a booking request. You need a free Rento account, which takes a minute to create. We confirm the booking and you can follow its status under <strong>My bookings</strong>.</p>
<h3>Do I pay online when I book?</h3>
<p>No. Sending a booking request is free. We first confirm that the car is available for your dates, then get in touch about pickup and payment.</p>
<h3>How is the price calculated?</h3>
<p>Every car has one daily price in Nepalese rupees. Your total is the number of days multiplied by the daily price, and a same-day rental counts as one day. You see the total before you send the request.</p>
<h3>How do I know if a car is free on my dates?</h3>
<p>When you send a request, we check the car&#39;s other bookings straight away. If any of your days are already taken, you are told immediately and can pick other dates or another car.</p>
<h3>Can I change or cancel a booking?</h3>
<p>Yes. Contact us with your booking number (shown under My bookings) and we will help you change or cancel it. Cancelled bookings are marked as Cancelled in your account.</p>
<h3>What do I need to bring?</h3>
<p>Please bring a valid driving licence and a photo ID. We confirm any other requirements for your trip when we confirm the booking.</p>
<h3>Which car should I choose?</h3>
<p>Hatchbacks are easy to park and light on fuel in the city, sedans are comfortable on highways, and SUVs handle hills and rough roads. Not sure? Use <a href="car-recommendation">Find my car</a> and we will suggest the best matches for your budget.</p>
<h3>How can I contact Rento?</h3>
<p>Call us, email us or send a message from the <a href="contact-us">contact page</a>.</p>'
WHERE type = 'faqs';
