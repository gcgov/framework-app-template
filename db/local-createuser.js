const server = "localhost"
const adminUsername = "administrator"
const adminPassword = ""
const targetDatabase = ""
const databaseUsernameToCreate = ""
const databasePasswordToCreate = ""

db = connect(server+":27017/admin");

db.auth(username,password);

db = db.getSiblingDB(targetDatabase);

db.createUser(
    {
        user: databaseUsernameToCreate,
        pwd: databasePasswordToCreate,
        roles: [ "readWrite" ]
    }
);