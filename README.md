createPatient():

POST - http://localhost/diet-api/patients

body:

{
    "first_name" : "Ivan",
    "last_name": "Statev"
}

============================================================================================

getPatients():

GET - http://localhost/diet-api/patients

============================================================================================

addFood():

POTS - http://localhost/diet-api/foods
body:
{
    "name" : Fries,
    "calories_per_100g" : 234
}

============================================================================================

removeFood($food_id):

DELETE - http://localhost/diet-api/foods/{food_id}

============================================================================================

addLog():

POST - http://localhost/diet-api/logs
body:
{
    "patient_id" : 1,
    "food_id":2,
    "quantity":10,
    "consumed_at" : "2025-02-16"
}

============================================================================================

updateLog($log_id):

PUT - http://localhost/diet-api/logs/{log_id}
body:
{
	"food_id" : {id},
	"consumed_at" : {date, format is Y-m-d},
	"quantity" : 123253345

}

============================================================================================

deleteLog($log_id):

DELETE - http://localhost/diet-api/logs/{log_id}

============================================================================================

getTotalCalories($patient_id):

GET - http://localhost/diet-api/patients/{patient_id}/calories?date={date/fallback:today}
